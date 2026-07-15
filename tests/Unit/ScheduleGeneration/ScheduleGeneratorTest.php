<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\ScheduleGenerator;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class ScheduleGeneratorTest extends TestCase
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

    private function dates(int $count): array
    {
        $dates = [];
        $date = new \DateTimeImmutable('2026-07-06');

        for ($i = 0; $i < $count; $i++) {
            $dates[] = $date;
            $date = $date->add(new \DateInterval('P7D'));
        }

        return $dates;
    }

    private function generator(int $seed): ScheduleGenerator
    {
        return new ScheduleGenerator(new SeededRng($seed), new ScheduleScorer);
    }

    public function test_valid_schedule_satisfies_all_hard_constraints_for_a_realistic_league()
    {
        $result = $this->generator(1)->generate(
            $this->dates(10),
            $this->teams(1, 2, 3, 4, 5, 6),
            $this->venues(10, 20),
            new GenerationConfig,
        );

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
        $this->assertSame([], $result->report->hardViolations);
    }

    public function test_same_seed_and_inputs_produce_identical_output()
    {
        $teams = $this->teams(1, 2, 3, 4, 5, 6);
        $venues = $this->venues(10, 20);
        $dates = $this->dates(8);

        $resultA = $this->generator(42)->generate($dates, $teams, $venues, new GenerationConfig);
        $resultB = $this->generator(42)->generate($dates, $teams, $venues, new GenerationConfig);

        $this->assertEquals($resultA->candidate, $resultB->candidate);
        $this->assertSame($resultA->attemptsUsed, $resultB->attemptsUsed);
    }

    public function test_odd_team_count_byes_are_evenly_rotated()
    {
        // 5 active teams, 1 venue -> capacity 2 matches/round (4 teams), 1 bye/round.
        $result = $this->generator(7)->generate(
            $this->dates(10),
            $this->teams(1, 2, 3, 4, 5),
            $this->venues(10),
            new GenerationConfig,
        );

        $byeCounts = array_fill_keys([1, 2, 3, 4, 5], 0);

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->byeTeamIds as $teamId) {
                $byeCounts[$teamId]++;
            }
        }

        $this->assertLessThanOrEqual(1, max($byeCounts) - min($byeCounts), 'bye counts should differ by at most 1 across the schedule');
    }

    public function test_inactive_teams_and_venues_can_never_appear_because_they_are_not_in_the_input()
    {
        // Only active teams/venues are ever passed in, so there is nothing for
        // the generator to select from except them - this is what makes H2
        // structural rather than merely scored.
        $activeTeams = $this->teams(1, 2, 3, 4);
        $activeVenues = $this->venues(10, 20);

        $result = $this->generator(3)->generate($this->dates(6), $activeTeams, $activeVenues, new GenerationConfig);

        $allowedTeamIds = [1, 2, 3, 4];
        $allowedVenueIds = [10, 20];

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                $this->assertContains($match->homeTeamId, $allowedTeamIds);
                $this->assertContains($match->awayTeamId, $allowedTeamIds);
                $this->assertContains($match->venueId, $allowedVenueIds);
            }

            foreach ($round->byeTeamIds as $teamId) {
                $this->assertContains($teamId, $allowedTeamIds);
            }
        }
    }

    public function test_fewer_than_two_active_teams_is_reported_as_degenerate()
    {
        $result = $this->generator(1)->generate($this->dates(4), $this->teams(1), $this->venues(10), new GenerationConfig);

        $this->assertTrue($result->report->degenerate);
        $this->assertNotNull($result->report->degenerateReason);
        // No matches at all should have been scheduled.
        foreach ($result->candidate->rounds as $round) {
            $this->assertSame([], $round->matches);
        }
    }

    public function test_no_active_venues_is_reported_as_degenerate()
    {
        $result = $this->generator(1)->generate($this->dates(4), $this->teams(1, 2), [], new GenerationConfig);

        $this->assertTrue($result->report->degenerate);
        $this->assertNotNull($result->report->degenerateReason);
    }

    public function test_no_round_dates_is_reported_as_degenerate_with_an_empty_schedule()
    {
        $result = $this->generator(1)->generate([], $this->teams(1, 2), $this->venues(10), new GenerationConfig);

        $this->assertTrue($result->report->degenerate);
        $this->assertSame([], $result->candidate->rounds);
    }

    public function test_exactly_two_teams_over_many_rounds_is_reported_as_degenerate()
    {
        // With only 2 active teams, they are forced to face each other every
        // round they both play - the no-repeat-opponent hard constraint can
        // never hold across consecutive played rounds, so this must be
        // surfaced honestly rather than silently committing an invalid
        // schedule.
        $config = new GenerationConfig(maxAttempts: 20, timeBudgetMs: 500);

        $result = $this->generator(9)->generate($this->dates(5), $this->teams(1, 2), $this->venues(10), $config);

        $this->assertTrue($result->report->degenerate);
        $this->assertFalse($result->report->hardConstraintsSatisfied);
    }

    public function test_respects_the_max_attempts_budget()
    {
        $config = new GenerationConfig(maxAttempts: 3, timeBudgetMs: 60_000);

        $result = $this->generator(5)->generate($this->dates(5), $this->teams(1, 2), $this->venues(10), $config);

        $this->assertLessThanOrEqual(3, $result->attemptsUsed);
    }

    public function test_a_team_is_never_away_when_the_match_is_at_their_own_home_venue()
    {
        // Teams 1-4 each own one of the 4 active venues; teams 5-6 have none.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => null, 6 => null]);
        $venues = $this->venues(100, 200, 300, 400);

        $result = $this->generator(11)->generate($this->dates(12), $teams, $venues, new GenerationConfig);

        $homeVenueIdByTeam = [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => null, 6 => null];

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                $this->assertNotSame(
                    $homeVenueIdByTeam[$match->awayTeamId],
                    $match->venueId,
                    "team #{$match->awayTeamId} was away at their own home venue"
                );
            }
        }
    }

    public function test_teams_with_a_home_venue_get_roughly_half_their_matches_at_home()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);

        $result = $this->generator(30)->generate($this->dates(16), $teams, $venues, new GenerationConfig);

        $homeVenueIdByTeam = [1 => 100, 2 => 200, 3 => 300, 4 => 400];
        $homeAppearances = array_fill_keys(array_keys($homeVenueIdByTeam), 0);
        $matchesPlayed = array_fill_keys(array_keys($homeVenueIdByTeam), 0);

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
                    if (isset($matchesPlayed[$teamId])) {
                        $matchesPlayed[$teamId]++;
                    }
                }

                if ($match->venueId === ($homeVenueIdByTeam[$match->homeTeamId] ?? null)) {
                    $homeAppearances[$match->homeTeamId]++;
                }
            }
        }

        foreach ($homeVenueIdByTeam as $teamId => $venueId) {
            $ratio = $homeAppearances[$teamId] / max(1, $matchesPlayed[$teamId]);
            $this->assertGreaterThan(0.25, $ratio, "team #{$teamId} played at home too rarely ({$homeAppearances[$teamId]}/{$matchesPlayed[$teamId]})");
            $this->assertLessThan(0.75, $ratio, "team #{$teamId} played at home too often ({$homeAppearances[$teamId]}/{$matchesPlayed[$teamId]})");
        }
    }

    public function test_two_teams_sharing_a_home_venue_still_produce_a_valid_schedule()
    {
        // Mirrors real data: two active teams can point at the same venue_id.
        // Uses 6 teams (not just the 2 sharing a venue) so the no-repeat-
        // opponent search has enough room to always succeed - a bare 4-team
        // pool is tight enough that the greedy pairing occasionally exhausts
        // its attempt budget regardless of the home-venue feature.
        $teams = $this->teamsWithHomeVenues([1 => 500, 2 => 500, 3 => null, 4 => null, 5 => null, 6 => null]);
        $venues = $this->venues(500, 600);

        $result = $this->generator(33)->generate($this->dates(10), $teams, $venues, new GenerationConfig);

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
    }
}
