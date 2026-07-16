<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\RoundCandidate;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class ScheduleScorerTest extends TestCase
{
    private function teams(int ...$ids): array
    {
        return array_map(fn (int $id) => new TeamInput($id, "Team {$id}"), $ids);
    }

    /**
     * @param array<int, int|null> $homeVenueIdByTeamId
     */
    private function teamsWithHomeVenues(array $homeVenueIdByTeamId): array
    {
        return array_map(
            fn (int $id, ?int $venueId) => new TeamInput($id, "Team {$id}", $venueId),
            array_keys($homeVenueIdByTeamId),
            array_values($homeVenueIdByTeamId),
        );
    }

    private function venues(int ...$ids): array
    {
        return array_map(fn (int $id) => new VenueInput($id, "Venue {$id}"), $ids);
    }

    private function date(string $ymd): \DateTimeImmutable
    {
        return new \DateTimeImmutable($ymd);
    }

    public function test_clean_single_round_candidate_scores_zero_and_passes_hard_constraints()
    {
        // With no prior round, there's no history to violate: no possible
        // opponent/venue repeat, matches played are equal (everyone plays
        // once), and a single home/away assignment is within the ±1 tolerance.
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(10, 'Venue 10', 1, 2),
                new MatchCandidate(20, 'Venue 20', 3, 4),
            ], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertTrue($report->hardConstraintsSatisfied);
        $this->assertSame([], $report->hardViolations);
        $this->assertSame(0.0, $report->score);
    }

    public function test_back_to_back_opponent_repeat_is_a_hard_violation()
    {
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(10, 'Venue 10', 1, 2),
                new MatchCandidate(20, 'Venue 20', 3, 4),
            ], []),
            new RoundCandidate($this->date('2026-07-13'), [
                new MatchCandidate(10, 'Venue 10', 2, 1),
                new MatchCandidate(20, 'Venue 20', 3, 4),
            ], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertFalse($report->hardConstraintsSatisfied);
        $this->assertNotEmpty(array_filter(
            $report->hardViolations,
            fn (string $v) => str_contains($v, 'consecutive rounds'),
        ));
    }

    public function test_inactive_team_assigned_is_a_hard_violation()
    {
        $teams = $this->teams(1, 2, 3); // team 4 is not in the active set
        $venues = $this->venues(10);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(10, 'Venue 10', 1, 4),
            ], [2, 3]),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertFalse($report->hardConstraintsSatisfied);
        $this->assertNotEmpty(array_filter(
            $report->hardViolations,
            fn (string $v) => str_contains($v, 'unknown team #4'),
        ));
    }

    public function test_inactive_venue_used_is_a_hard_violation()
    {
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10); // venue 99 is not in the active set

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(99, 'Venue 99', 1, 2),
            ], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertFalse($report->hardConstraintsSatisfied);
        $this->assertNotEmpty(array_filter(
            $report->hardViolations,
            fn (string $v) => str_contains($v, 'unknown venue #99'),
        ));
    }

    public function test_team_double_booked_in_one_round_is_a_hard_violation()
    {
        $teams = $this->teams(1, 2, 3);
        $venues = $this->venues(10, 20);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(10, 'Venue 10', 1, 2),
                new MatchCandidate(20, 'Venue 20', 1, 3),
            ], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertFalse($report->hardConstraintsSatisfied);
    }

    public function test_team_both_byed_and_matched_is_a_hard_violation()
    {
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(10, 'Venue 10', 1, 2),
            ], [1]),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertFalse($report->hardConstraintsSatisfied);
    }

    public function test_two_different_teams_each_hitting_a_consecutive_same_venue_once_scores_the_base_penalty_for_each()
    {
        // Every consecutive-same-venue occurrence is a real break and
        // always costs weightVenue, no matter which team or how many other
        // teams also have one - two different teams each hit once is
        // 2x weightVenue, the same as it would be added up individually.
        // What's NOT charged here is any *extra* repeat-offense surcharge,
        // since neither team was hit twice - see
        // test_the_same_team_hitting_consecutive_same_venue_twice_costs_more_than_two_isolated_incidents
        // below for the case that does add a surcharge.
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);
        $config = new GenerationConfig(weightVenue: 5.0, weightEquality: 0.0, weightRepeat: 0.0, weightHomeAway: 0.0);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(10, 'Venue 10', 1, 2),
                new MatchCandidate(20, 'Venue 20', 3, 4),
            ], []),
            new RoundCandidate($this->date('2026-07-13'), [
                new MatchCandidate(10, 'Venue 10', 1, 3),
                new MatchCandidate(20, 'Venue 20', 2, 4),
            ], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        $this->assertTrue($report->hardConstraintsSatisfied);
        // Team 1 (1 occurrence) + team 4 (1 occurrence) = 2 x weightVenue,
        // no repeat-offense surcharge since neither team was hit twice.
        $this->assertSame(10.0, $report->score);
        $this->assertArrayHasKey('consecutive_venue', $report->softViolationsByCriterion);
        $this->assertCount(2, $report->softViolationsByCriterion['consecutive_venue']);
        $messages = implode(' ', $report->softViolationsByCriterion['consecutive_venue']);
        $this->assertStringContainsString('Team 1', $messages);
        $this->assertStringContainsString('Team 4', $messages);
    }

    public function test_the_same_team_hitting_consecutive_same_venue_twice_costs_more_than_two_isolated_incidents()
    {
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20, 30);
        $config = new GenerationConfig(weightVenue: 5.0, weightEquality: 0.0, weightRepeat: 0.0, weightHomeAway: 0.0);

        // Team 1 is at venue 10 for rounds 1-2 (incident #1), then at venue
        // 20 for rounds 3-4 (incident #2) - two separate repeat-offense
        // events for the same team, not a single 3-round streak, which
        // must count identically (see ScheduleScorer for why). Every other
        // team's venue sequence alternates cleanly except team 4, which has
        // exactly one isolated incident.
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(10, 'Venue 10', 1, 2),
                new MatchCandidate(30, 'Venue 30', 3, 4),
            ], []),
            new RoundCandidate($this->date('2026-07-13'), [
                new MatchCandidate(10, 'Venue 10', 1, 3),
                new MatchCandidate(20, 'Venue 20', 2, 4),
            ], []),
            new RoundCandidate($this->date('2026-07-20'), [
                new MatchCandidate(20, 'Venue 20', 1, 4),
                new MatchCandidate(30, 'Venue 30', 2, 3),
            ], []),
            new RoundCandidate($this->date('2026-07-27'), [
                new MatchCandidate(20, 'Venue 20', 1, 2),
                new MatchCandidate(10, 'Venue 10', 3, 4),
            ], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        $this->assertTrue($report->hardConstraintsSatisfied);
        // Team 1: 2 occurrences -> base 2 x weightVenue + repeat-offense
        // surcharge of 1 x weightVenue = 3 x weightVenue = 15.0.
        // Team 4: 1 occurrence -> base 1 x weightVenue = 5.0, no surcharge.
        // Total = 20.0 - strictly more than two teams each hit once would
        // cost (10.0), which is the whole point of the surcharge: the same
        // team being hit twice is treated as worse than the incident count
        // spread across different teams.
        $this->assertSame(20.0, $report->score);
        $this->assertArrayHasKey('consecutive_venue', $report->softViolationsByCriterion);
        $this->assertCount(3, $report->softViolationsByCriterion['consecutive_venue']);
        $messages = implode(' ', $report->softViolationsByCriterion['consecutive_venue']);
        $this->assertSame(2, substr_count($messages, 'Team 1 played'), 'team 1 should be reported for both of its incidents');
        $this->assertSame(1, substr_count($messages, 'Team 4 played'), 'team 4 should be reported for its one tolerated incident');
    }

    public function test_unequal_matches_played_is_a_soft_penalty()
    {
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10);
        $config = new GenerationConfig(weightVenue: 0.0, weightEquality: 8.0, weightRepeat: 0.0, weightHomeAway: 0.0);

        // Byes rotate unevenly here on purpose: 1/2 play twice, 3/4 play once.
        // A bye between a team's matches resets its "last opponent", so the
        // repeated (1v2) pairing across rounds 1 and 3 is not a back-to-back
        // repeat (round 2 was a bye for both) and stays hard-constraint clean.
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(10, 'Venue 10', 1, 2),
            ], [3, 4]),
            new RoundCandidate($this->date('2026-07-13'), [
                new MatchCandidate(10, 'Venue 10', 3, 4),
            ], [1, 2]),
            new RoundCandidate($this->date('2026-07-20'), [
                new MatchCandidate(10, 'Venue 10', 1, 2),
            ], [3, 4]),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        $this->assertTrue($report->hardConstraintsSatisfied);
        // Teams 1/2 played 2 matches, teams 3/4 played 1 -> spread of 1.
        $this->assertSame(8.0, $report->score);
        $this->assertArrayHasKey('equal_matches_played', $report->softViolationsByCriterion);
    }

    public function test_home_away_imbalance_beyond_one_is_a_soft_penalty()
    {
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);
        $config = new GenerationConfig(weightVenue: 0.0, weightEquality: 0.0, weightRepeat: 0.0, weightHomeAway: 2.0);

        // Team 1 is home 3 times in a row (diff of 3, over by 2 once |diff|-1 = 2).
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        // Team 1: 3 home / 0 away -> |diff|=3, over the ±1 tolerance by 2.
        // Team 2: 0 home / 3 away -> same imbalance from the other side.
        $this->assertSame(8.0, $report->score);
        $this->assertArrayHasKey('home_away_balance', $report->softViolationsByCriterion);
        $this->assertCount(2, $report->softViolationsByCriterion['home_away_balance']);
    }

    public function test_team_assigned_away_at_their_own_home_venue_is_a_hard_violation()
    {
        // Team 1's home venue is 100. A match at venue 100 with team 1 on the
        // away side violates "never away at your own venue" - this can't
        // happen from generator output (construction always makes the home-
        // venue-owning team the host), but the scorer must still catch it.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => null]);
        $venues = $this->venues(100);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [
                new MatchCandidate(100, 'Venue 100', 2, 1),
            ], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertFalse($report->hardConstraintsSatisfied);
        $this->assertNotEmpty(array_filter(
            $report->hardViolations,
            fn (string $v) => str_contains($v, 'own home venue'),
        ));
    }

    public function test_never_playing_at_ones_own_home_venue_is_a_soft_penalty()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => null, 3 => null, 4 => null]);
        $venues = $this->venues(100, 300);
        $config = new GenerationConfig(weightVenue: 0.0, weightEquality: 0.0, weightRepeat: 0.0, weightHomeAway: 0.0, weightHomeVenueBalance: 6.0);

        // Team 1 has a home venue (100) but is always sent away to a neutral
        // venue (300) instead, across 4 rounds with varying opponents. Teams
        // sitting out a round are explicitly byed so their opponent/venue
        // history resets - matching the invariant real generator output
        // maintains (every active team either plays or is byed each round).
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(300, 'Venue 300', 2, 1)], [3, 4]),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(300, 'Venue 300', 3, 1)], [2, 4]),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(300, 'Venue 300', 4, 1)], [2, 3]),
            new RoundCandidate($this->date('2026-07-27'), [new MatchCandidate(300, 'Venue 300', 2, 1)], [3, 4]),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        $this->assertTrue($report->hardConstraintsSatisfied);
        // Team 1: 0 home-venue appearances out of 4 matches -> diff=4, over the
        // ±1 tolerance by 3 -> penalty = 6 * 3 = 18.
        $this->assertSame(18.0, $report->score);
        $this->assertArrayHasKey('home_venue_balance', $report->softViolationsByCriterion);
        $this->assertCount(1, $report->softViolationsByCriterion['home_venue_balance']);
    }

    public function test_roughly_balanced_home_venue_appearances_score_zero_for_that_criterion()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => null, 3 => null]);
        $venues = $this->venues(100, 300);
        $config = new GenerationConfig(weightVenue: 0.0, weightEquality: 0.0, weightRepeat: 0.0, weightHomeAway: 0.0, weightHomeVenueBalance: 6.0);

        // Team 1 plays at its own home venue (100) half the time and away
        // (300) the other half - within the ±1 tolerance, no penalty.
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(100, 'Venue 100', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(300, 'Venue 300', 3, 1)], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        $this->assertTrue($report->hardConstraintsSatisfied);
        $this->assertSame(0.0, $report->score);
        $this->assertArrayNotHasKey('home_venue_balance', $report->softViolationsByCriterion);
    }

    public function test_violation_messages_use_team_names_not_generic_ids()
    {
        $teams = [
            new TeamInput(1, 'Buttermilk Team'),
            new TeamInput(2, "Rullo's Team"),
        ];
        $venues = $this->venues(10, 20);
        $config = new GenerationConfig(weightVenue: 5.0, weightEquality: 0.0, weightRepeat: 0.0, weightHomeAway: 0.0, weightHomeVenueBalance: 0.0);

        // Three rounds at the same venue is two incidents for each team
        // (round1-2, then round2-3) - over the one-incident tolerance, so
        // this actually produces a consecutive_venue message to check names on.
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(10, 'Venue 10', 2, 1)], []),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        $messages = implode(' ', $report->softViolationsByCriterion['consecutive_venue']);
        $this->assertStringContainsString('Buttermilk Team', $messages);
        $this->assertStringContainsString("Rullo's Team", $messages);
        $this->assertStringNotContainsString('#1', $messages);
        $this->assertStringNotContainsString('#2', $messages);
    }
}
