<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\GenerationReport;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\RoundCandidate;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class ScheduleScorerTest extends TestCase
{
    /**
     * Some criteria (consecutive_venue, home_away_break) share the same
     * underlying match data, so isolating one via zeroed weights no longer
     * works now that their weight is dynamic (team-count-derived) rather
     * than config-driven. Reading a single criterion's own score directly
     * sidesteps that entirely.
     */
    private function criterionScore(GenerationReport $report, string $key): float
    {
        foreach ($report->softCriteriaScores as $criterion) {
            if ($criterion['key'] === $key) {
                return $criterion['score'];
            }
        }

        $this->fail("No soft criterion scored under key '{$key}'.");
    }

    /**
     * Config-independent, pre-weight value - see rawPenalty() on
     * SoftCriterion. Used by the epsilon-constraint tests below.
     */
    private function criterionRaw(GenerationReport $report, string $key): float
    {
        foreach ($report->softCriteriaScores as $criterion) {
            if ($criterion['key'] === $key) {
                return $criterion['raw'];
            }
        }

        $this->fail("No soft criterion scored under key '{$key}'.");
    }

    private function criterionEpsilonUnit(GenerationReport $report, string $key): float
    {
        foreach ($report->softCriteriaScores as $criterion) {
            if ($criterion['key'] === $key) {
                return $criterion['epsilonUnit'];
            }
        }

        $this->fail("No soft criterion scored under key '{$key}'.");
    }

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

    public function test_back_to_back_opponent_repeat_is_a_soft_penalty()
    {
        // No longer a hard constraint - RoundBuilder used to actively avoid
        // this and ScheduleScorer used to reject the candidate outright; now
        // it's just penalized like any other soft criterion.
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);

        // Both pairs repeat their round-1 matchup in round 2 (1v2 and 3v4
        // each meet again immediately) - two occurrences total, out of 4
        // matches observed (matchCount=4), so normalized = 2/4 = 0.5.
        // tierWeight('repeat_opponent_consecutive_rounds') is 100^3 = 1e6
        // under the default priority order (see GenerationConfig).
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

        $this->assertTrue($report->hardConstraintsSatisfied);
        $this->assertEqualsWithDelta(500_000.0, $this->criterionScore($report, 'repeat_opponent_consecutive_rounds'), 0.01);
        $this->assertArrayHasKey('repeat_opponent_consecutive_rounds', $report->softViolationsByCriterion);
        $this->assertCount(2, $report->softViolationsByCriterion['repeat_opponent_consecutive_rounds']);
        $messages = implode(' ', $report->softViolationsByCriterion['repeat_opponent_consecutive_rounds']);
        $this->assertStringContainsString('consecutive rounds', $messages);
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
        // Every consecutive-same-venue occurrence is a real break and always
        // costs one raw unit - no matter which team or how many other teams
        // also have one: two different teams each hit once is 2 raw units,
        // the same as it would be added up individually. What's NOT charged
        // here is any *extra* repeat-offense surcharge, since neither team
        // was hit twice - see
        // test_the_same_team_hitting_consecutive_same_venue_twice_costs_more_than_two_isolated_incidents
        // below for the case that does add a surcharge. Checked via the
        // criterion's own score (not the report total), since this candidate
        // also happens to trigger home_away_break for teams 1 and 4 (they
        // keep the same venue by staying in the same home/away role too) -
        // irrelevant noise for this test.
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);

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

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertTrue($report->hardConstraintsSatisfied);
        // Team 1 (1 occurrence) + team 4 (1 occurrence) = 2 raw units, no
        // repeat-offense surcharge since neither team was hit twice.
        // matchCount=4, normalized = 2/(2x4) = 0.25; tierWeight is 100^0 = 1
        // (consecutive_venue is last in the default priority order).
        $this->assertSame(0.25, $this->criterionScore($report, 'consecutive_venue'));
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

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertTrue($report->hardConstraintsSatisfied);
        // Team 1: 2 occurrences -> base 2 raw units + repeat-offense
        // surcharge of 1 raw unit = 3 raw units.
        // Team 4: 1 occurrence -> base 1 raw unit, no surcharge.
        // rawTotal = 4; matchCount = 8 (2 matches x 4 rounds), normalized =
        // 4/(2x8) = 0.25 - strictly more than two teams each hit once would
        // cost in the same shape (which is the whole point of the surcharge:
        // the same team being hit twice is treated as worse than the
        // incident count spread across different teams). Checked via the
        // criterion's own score, not the report total (see the previous
        // test's comment).
        $this->assertSame(0.25, $this->criterionScore($report, 'consecutive_venue'));
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

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertTrue($report->hardConstraintsSatisfied);
        // Teams 1/2 played 2 matches, teams 3/4 played 1 -> spread of 1,
        // over 3 rounds seen -> normalized = 1/3. tierWeight is 100^6 = 1e12
        // (equal_matches_played is highest priority in the default order).
        $this->assertEqualsWithDelta(1_000_000_000_000 * (1 / 3), $this->criterionScore($report, 'equal_matches_played'), 0.01);
        $this->assertArrayHasKey('equal_matches_played', $report->softViolationsByCriterion);
    }

    public function test_home_away_imbalance_beyond_one_is_a_soft_penalty()
    {
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);

        // Team 1 is home 3 times in a row (diff of 3, over by 2 once |diff|-1 = 2).
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        // Team 1: 3 home / 0 away -> |diff|=3, over the ±1 tolerance by 2.
        // Team 2: 0 home / 3 away -> same imbalance from the other side.
        // totalOver=4, teamCount=2, normalized=2; tierWeight is 100^5 = 1e10
        // (home_away_balance is 2nd-highest priority in the default order).
        // Checked via the criterion's own score, not the report total, since
        // this candidate (same match repeated 3 rounds) also triggers other
        // criteria now that every criterion has a real, nonzero weight.
        $this->assertSame(20_000_000_000.0, $this->criterionScore($report, 'home_away_balance'));
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

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertTrue($report->hardConstraintsSatisfied);
        // Team 1: 0 home-venue appearances out of 4 matches -> diff=4, over
        // the ±1 tolerance by 3 -> totalOver=3, teamCount=4, normalized=0.75.
        // tierWeight is 100^4 = 1e8 (home_venue_balance is 3rd-highest
        // priority in the default order). Checked via the criterion's own
        // score, not the report total, since team 1 also racks up
        // consecutive_venue and home_away_break occurrences here (always
        // sent to the same neutral venue in the same away role).
        $this->assertSame(75_000_000.0, $this->criterionScore($report, 'home_venue_balance'));
        $this->assertArrayHasKey('home_venue_balance', $report->softViolationsByCriterion);
        $this->assertCount(1, $report->softViolationsByCriterion['home_venue_balance']);
    }

    public function test_roughly_balanced_home_venue_appearances_score_zero_for_that_criterion()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => null, 3 => null]);
        $venues = $this->venues(100, 300);

        // Team 1 plays at its own home venue (100) half the time and away
        // (300) the other half - within the ±1 tolerance, no penalty.
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(100, 'Venue 100', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(300, 'Venue 300', 3, 1)], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertTrue($report->hardConstraintsSatisfied);
        $this->assertSame(0.0, $this->criterionScore($report, 'home_venue_balance'));
        $this->assertArrayNotHasKey('home_venue_balance', $report->softViolationsByCriterion);
    }

    public function test_violation_messages_use_team_names_not_generic_ids()
    {
        $teams = [
            new TeamInput(1, 'Buttermilk Team'),
            new TeamInput(2, "Rullo's Team"),
        ];
        $venues = $this->venues(10, 20);

        // Three rounds at the same venue is two incidents for each team
        // (round1-2, then round2-3) - over the one-incident tolerance, so
        // this actually produces a consecutive_venue message to check names on.
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(10, 'Venue 10', 2, 1)], []),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $messages = implode(' ', $report->softViolationsByCriterion['consecutive_venue']);
        $this->assertStringContainsString('Buttermilk Team', $messages);
        $this->assertStringContainsString("Rullo's Team", $messages);
        $this->assertStringNotContainsString('#1', $messages);
        $this->assertStringNotContainsString('#2', $messages);
    }

    public function test_playing_the_same_home_away_role_in_consecutive_rounds_is_a_soft_penalty_in_both_directions()
    {
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);

        // Team 1 is home in both rounds (a home/home break) and team 4 is
        // away in both rounds (an away/away break) - teams 2 and 3 properly
        // alternate role between the two rounds, so only these two breaks
        // should be counted, one per direction.
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

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        // breakCount=2, matchCount=4 (2 matches x 2 rounds), normalized =
        // 2/(2x4) = 0.25; tierWeight is 100^1 = 100 (home_away_break is
        // 2nd-lowest priority in the default order), so 100 x 0.25 = 25.
        // Checked via the criterion's own score, not the report total, since
        // this candidate also happens to trigger consecutive_venue for teams
        // 1 and 4 (same venue as their role).
        $this->assertSame(25.0, $this->criterionScore($report, 'home_away_break'));
        $this->assertArrayHasKey('home_away_break', $report->softViolationsByCriterion);
        $this->assertCount(2, $report->softViolationsByCriterion['home_away_break']);
        $messages = implode(' ', $report->softViolationsByCriterion['home_away_break']);
        $this->assertStringContainsString('Team 1 played home in consecutive rounds', $messages);
        $this->assertStringContainsString('Team 4 played away in consecutive rounds', $messages);
    }

    public function test_a_bye_resets_the_home_away_role_streak()
    {
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10);

        // Team 1 is home in rounds 1 and 3, but byes round 2 in between -
        // that bye should reset team 1's (and team 2's) "last role" the same
        // way it resets last-opponent/last-venue elsewhere, so this must not
        // count as a repeat.
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], [3, 4]),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(10, 'Venue 10', 3, 4)], [1, 2]),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(10, 'Venue 10', 1, 2)], [3, 4]),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertSame(0.0, $this->criterionScore($report, 'home_away_break'));
        $this->assertArrayNotHasKey('home_away_break', $report->softViolationsByCriterion);
    }

    public function test_home_away_break_penalty_is_flat_with_no_repeat_offense_surcharge()
    {
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);

        // Team 1 is home for all 3 rounds (2 consecutive-round transitions:
        // round1->2, round2->3), team 2 is away for all 3 - 4 break events
        // total (2 per team). Unlike ConsecutiveVenueCriterion, this is a
        // flat count with no escalating surcharge for repeat offenders.
        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        // breakCount=4, matchCount=3 (1 match x 3 rounds), normalized =
        // 4/(2x3) = 0.6667; tierWeight is 100. Checked via the criterion's
        // own score, not the report total, since this candidate also
        // triggers consecutive_venue (same venue every round).
        $this->assertEqualsWithDelta(100 * (4 / 6), $this->criterionScore($report, 'home_away_break'), 0.01);
        $this->assertCount(4, $report->softViolationsByCriterion['home_away_break']);
    }

    public function test_consecutive_venue_raw_and_epsilon_unit_are_normalized_by_two_times_match_count()
    {
        // Same candidate as test_two_different_teams_each_hitting_a_consecutive_same_venue_once...:
        // 2 raw units (team 1 + team 4, no repeat-offense surcharge), matchCount=4.
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);

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

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertEqualsWithDelta(2 / 8, $this->criterionRaw($report, 'consecutive_venue'), 0.0001);
        $this->assertEqualsWithDelta(1 / 8, $this->criterionEpsilonUnit($report, 'consecutive_venue'), 0.0001);
    }

    public function test_equal_matches_played_raw_and_epsilon_unit_are_normalized_by_rounds_seen()
    {
        // Same candidate as test_unequal_matches_played_is_a_soft_penalty:
        // spread=1, roundsSeen=3.
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10);

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

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertEqualsWithDelta(1 / 3, $this->criterionRaw($report, 'equal_matches_played'), 0.0001);
        $this->assertEqualsWithDelta(1 / 3, $this->criterionEpsilonUnit($report, 'equal_matches_played'), 0.0001);
    }

    public function test_home_away_balance_raw_and_epsilon_unit_are_normalized_by_team_count()
    {
        // Same candidate as test_home_away_imbalance_beyond_one_is_a_soft_penalty:
        // totalOver=4 (2 per team), teamCount=2.
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertEqualsWithDelta(4 / 2, $this->criterionRaw($report, 'home_away_balance'), 0.0001);
        $this->assertEqualsWithDelta(1 / 2, $this->criterionEpsilonUnit($report, 'home_away_balance'), 0.0001);
    }

    public function test_home_venue_balance_raw_and_epsilon_unit_are_normalized_by_team_count()
    {
        // Same candidate as test_never_playing_at_ones_own_home_venue_is_a_soft_penalty:
        // totalOver=3, teamCount=4.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => null, 3 => null, 4 => null]);
        $venues = $this->venues(100, 300);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(300, 'Venue 300', 2, 1)], [3, 4]),
            new RoundCandidate($this->date('2026-07-13'), [new MatchCandidate(300, 'Venue 300', 3, 1)], [2, 4]),
            new RoundCandidate($this->date('2026-07-20'), [new MatchCandidate(300, 'Venue 300', 4, 1)], [2, 3]),
            new RoundCandidate($this->date('2026-07-27'), [new MatchCandidate(300, 'Venue 300', 2, 1)], [3, 4]),
        ]);

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertEqualsWithDelta(3 / 4, $this->criterionRaw($report, 'home_venue_balance'), 0.0001);
        $this->assertEqualsWithDelta(1 / 4, $this->criterionEpsilonUnit($report, 'home_venue_balance'), 0.0001);
    }

    public function test_opponent_recency_raw_and_epsilon_unit_are_normalized_by_match_count_times_ideal_gap()
    {
        // Same candidate as test_back_to_back_opponent_repeat_is_a_soft_penalty:
        // both pairs rematch after a gap of 1 round. idealGap for 4 active
        // teams is ceil(4/2)=2, so each pair's shortfall is max(0,2-1)=1,
        // shortfallTotal=2. matchCount=4, divisor=4*2=8.
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

        $this->assertEqualsWithDelta(2 / 8, $this->criterionRaw($report, 'opponent_recency'), 0.0001);
        $this->assertEqualsWithDelta(1 / 8, $this->criterionEpsilonUnit($report, 'opponent_recency'), 0.0001);
    }

    public function test_home_away_break_raw_and_epsilon_unit_are_normalized_by_two_times_match_count()
    {
        // Same candidate as test_playing_the_same_home_away_role_in_consecutive_rounds_is_a_soft_penalty_in_both_directions:
        // breakCount=2, matchCount=4.
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);

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

        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, new GenerationConfig);

        $this->assertEqualsWithDelta(2 / 8, $this->criterionRaw($report, 'home_away_break'), 0.0001);
        $this->assertEqualsWithDelta(1 / 8, $this->criterionEpsilonUnit($report, 'home_away_break'), 0.0001);
    }

    public function test_repeat_opponent_consecutive_rounds_raw_and_epsilon_unit_are_normalized_by_match_count()
    {
        // Same candidate as test_back_to_back_opponent_repeat_is_a_soft_penalty:
        // repeatCount=2, matchCount=4.
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

        $this->assertEqualsWithDelta(2 / 4, $this->criterionRaw($report, 'repeat_opponent_consecutive_rounds'), 0.0001);
        $this->assertEqualsWithDelta(1 / 4, $this->criterionEpsilonUnit($report, 'repeat_opponent_consecutive_rounds'), 0.0001);
    }

    public function test_a_criterion_omitted_from_soft_criteria_is_not_evaluated_at_all()
    {
        // Same candidate as test_back_to_back_opponent_repeat_is_a_soft_penalty
        // (both pairs immediately rematch), which under the default config
        // triggers BOTH repeat_opponent_consecutive_rounds and
        // opponent_recency - but softCriteria here only enables
        // home_away_balance, so neither of those two should be evaluated at
        // all, not merely scored at zero.
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

        $config = new GenerationConfig(softCriteria: ['home_away_balance']);
        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        $this->assertSame(['home_away_balance'], array_column($report->softCriteriaScores, 'key'));
        $this->assertArrayNotHasKey('repeat_opponent_consecutive_rounds', $report->softViolationsByCriterion);
        $this->assertArrayNotHasKey('opponent_recency', $report->softViolationsByCriterion);
    }

    public function test_an_unknown_key_in_soft_criteria_is_silently_skipped_rather_than_erroring()
    {
        // GenerationConfig's constructor doesn't itself guarantee
        // sanitizeSoftCriteria() has run (that's only enforced by the
        // fromConfig()/forAssociation() factory methods) - ScheduleScorer
        // must tolerate an arbitrary array handed to it directly.
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);

        $candidate = new ScheduleCandidate([
            new RoundCandidate($this->date('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $config = new GenerationConfig(softCriteria: ['not_a_real_key']);
        $report = (new ScheduleScorer)->score($candidate, $teams, $venues, $config);

        $this->assertSame([], $report->softCriteriaScores);
        $this->assertSame(0.0, $report->score);
        $this->assertTrue($report->hardConstraintsSatisfied);
    }
}
