<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\RoundRobinConstructor;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class RoundRobinConstructorTest extends TestCase
{
    /**
     * @param array<int, int> $homeVenueIdByTeamId team id => venue id, in the order teams should be assigned to slots
     */
    private function teamsWithOwnVenues(array $homeVenueIdByTeamId): array
    {
        return array_map(
            fn (int $id, int $venueId) => new TeamInput($id, "Team {$id}", $venueId),
            array_keys($homeVenueIdByTeamId),
            array_values($homeVenueIdByTeamId),
        );
    }

    private function venuesFor(array $homeVenueIdByTeamId): array
    {
        return array_map(fn (int $venueId) => new VenueInput($venueId, "Venue {$venueId}"), array_values($homeVenueIdByTeamId));
    }

    /**
     * @param VenueInput[] $venues
     * @return RoundInput[]
     */
    private function rounds(int $count, array $venues): array
    {
        $rounds = [];
        $date = new \DateTimeImmutable('2026-07-06');
        $matchId = 1;

        for ($i = 0; $i < $count; $i++) {
            $slots = array_map(
                fn (VenueInput $venue) => new MatchSlotInput($matchId++, $venue->id, $venue->name),
                $venues,
            );

            $rounds[] = new RoundInput($i + 1, $date, $slots);
            $date = $date->add(new \DateInterval('P7D'));
        }

        return $rounds;
    }

    /**
     * Every team's home(1)/away(0) role per round it played, in round order.
     * (Byes simply leave no entry for that round for that team.)
     *
     * @return array<int, array<int, int>>
     */
    private function roleSequencesByTeam(ScheduleCandidate $candidate): array
    {
        $sequences = [];

        foreach ($candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                $sequences[$match->homeTeamId][] = 1;
                $sequences[$match->awayTeamId][] = 0;
            }
        }

        return $sequences;
    }

    private function totalAbstractBreaks(ScheduleCandidate $candidate): int
    {
        $breaks = 0;

        foreach ($this->roleSequencesByTeam($candidate) as $sequence) {
            for ($i = 1; $i < count($sequence); $i++) {
                if ($sequence[$i] === $sequence[$i - 1]) {
                    $breaks++;
                }
            }
        }

        return $breaks;
    }

    private function assertValidRoundRobin(ScheduleCandidate $candidate, array $teamIds): void
    {
        $metCount = [];
        $lastOpponent = [];

        foreach ($candidate->rounds as $roundIndex => $round) {
            $playedThisRound = [];

            foreach ($round->matches as $match) {
                foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
                    $this->assertArrayNotHasKey($teamId, $playedThisRound, "team {$teamId} double-booked in round {$roundIndex}");
                    $playedThisRound[$teamId] = true;
                }

                $key = $match->homeTeamId < $match->awayTeamId
                    ? "{$match->homeTeamId}-{$match->awayTeamId}"
                    : "{$match->awayTeamId}-{$match->homeTeamId}";
                $metCount[$key] = ($metCount[$key] ?? 0) + 1;
                $this->assertLessThanOrEqual(1, $metCount[$key], "pair {$key} met more than once within one cycle");

                $this->assertNotSame(
                    $lastOpponent[$match->homeTeamId] ?? null,
                    $match->awayTeamId,
                    "teams {$match->homeTeamId}/{$match->awayTeamId} played consecutive rounds (round {$roundIndex})"
                );
            }

            $currentOpponent = [];

            foreach ($round->matches as $match) {
                $currentOpponent[$match->homeTeamId] = $match->awayTeamId;
                $currentOpponent[$match->awayTeamId] = $match->homeTeamId;
            }

            foreach ($round->byeTeamIds as $byeTeamId) {
                unset($currentOpponent[$byeTeamId]);
                $lastOpponent[$byeTeamId] = null;
            }

            foreach ($currentOpponent as $teamId => $opponentId) {
                $lastOpponent[$teamId] = $opponentId;
            }
        }
    }

    public function test_even_team_count_single_cycle_achieves_the_theoretical_minimum_breaks()
    {
        foreach ([4, 6, 8] as $n) {
            $homeVenueIdByTeamId = [];
            for ($i = 1; $i <= $n; $i++) {
                $homeVenueIdByTeamId[$i] = 100 + $i;
            }

            $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
            $venues = $this->venuesFor($homeVenueIdByTeamId);

            $candidate = (new RoundRobinConstructor())->construct($this->rounds($n - 1, $venues), $teams, $venues);

            $this->assertNotNull($candidate, "n={$n} should be eligible");
            $this->assertCount($n - 1, $candidate->rounds, "n={$n} single cycle is N-1 rounds");

            $this->assertValidRoundRobin($candidate, array_keys($homeVenueIdByTeamId));

            $this->assertSame($n - 2, $this->totalAbstractBreaks($candidate), "n={$n} should hit the N-2 minimum-breaks bound");

            // H4: every match is played at the home team's own venue.
            foreach ($candidate->rounds as $round) {
                foreach ($round->matches as $match) {
                    $this->assertSame($homeVenueIdByTeamId[$match->homeTeamId], $match->venueId);
                }
            }

            // Home/away balanced to within 1 for every team.
            $homeCount = array_fill_keys(array_keys($homeVenueIdByTeamId), 0);
            $awayCount = array_fill_keys(array_keys($homeVenueIdByTeamId), 0);

            foreach ($candidate->rounds as $round) {
                foreach ($round->matches as $match) {
                    $homeCount[$match->homeTeamId]++;
                    $awayCount[$match->awayTeamId]++;
                }
            }

            foreach (array_keys($homeVenueIdByTeamId) as $teamId) {
                $this->assertLessThanOrEqual(1, abs($homeCount[$teamId] - $awayCount[$teamId]), "n={$n} team {$teamId} home/away imbalance");
            }
        }
    }

    public function test_odd_team_count_uses_a_phantom_bye_and_rotates_it_evenly()
    {
        $homeVenueIdByTeamId = [1 => 101, 2 => 102, 3 => 103, 4 => 104, 5 => 105];
        $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
        $venues = $this->venuesFor($homeVenueIdByTeamId);

        $candidate = (new RoundRobinConstructor())->construct($this->rounds(4, $venues), $teams, $venues);

        $this->assertNotNull($candidate);
        $this->assertCount(4, $candidate->rounds);

        $this->assertValidRoundRobin($candidate, array_keys($homeVenueIdByTeamId));

        $byeCounts = array_fill_keys(array_keys($homeVenueIdByTeamId), 0);

        foreach ($candidate->rounds as $round) {
            $this->assertCount(1, $round->byeTeamIds, 'exactly one bye per round for 5 teams');

            foreach ($round->byeTeamIds as $teamId) {
                $byeCounts[$teamId]++;
            }
        }

        $this->assertLessThanOrEqual(1, max($byeCounts) - min($byeCounts), 'byes should rotate evenly');
    }

    public function test_double_cycle_keeps_home_away_balanced_and_records_break_count()
    {
        $homeVenueIdByTeamId = [1 => 101, 2 => 102, 3 => 103, 4 => 104];
        $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
        $venues = $this->venuesFor($homeVenueIdByTeamId);

        $candidate = (new RoundRobinConstructor())->construct($this->rounds(6, $venues), $teams, $venues);

        $this->assertNotNull($candidate);
        $this->assertCount(6, $candidate->rounds);
        $this->assertValidRoundRobinAllowingRematchesAcrossCycles($candidate);

        $homeCount = array_fill_keys(array_keys($homeVenueIdByTeamId), 0);
        $awayCount = array_fill_keys(array_keys($homeVenueIdByTeamId), 0);

        foreach ($candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                $homeCount[$match->homeTeamId]++;
                $awayCount[$match->awayTeamId]++;
            }
        }

        foreach (array_keys($homeVenueIdByTeamId) as $teamId) {
            // A full mirrored double round-robin gives every team exactly
            // 3 home and 3 away matches across the 6 rounds.
            $this->assertSame(3, $homeCount[$teamId]);
            $this->assertSame(3, $awayCount[$teamId]);
        }

        // Mirroring two independently-optimal single-cycle passes costs more
        // than 2x(N-2) breaks because of the seam where the passes join -
        // see plan.md. This assertion records/locks in the actual observed
        // figure rather than asserting an unverified theoretical constant.
        $this->assertSame(6, $this->totalAbstractBreaks($candidate));
    }

    private function assertValidRoundRobinAllowingRematchesAcrossCycles(ScheduleCandidate $candidate): void
    {
        $lastOpponent = [];

        foreach ($candidate->rounds as $roundIndex => $round) {
            $playedThisRound = [];

            foreach ($round->matches as $match) {
                foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
                    $this->assertArrayNotHasKey($teamId, $playedThisRound, "team {$teamId} double-booked in round {$roundIndex}");
                    $playedThisRound[$teamId] = true;
                }

                $this->assertNotSame(
                    $lastOpponent[$match->homeTeamId] ?? null,
                    $match->awayTeamId,
                    "teams {$match->homeTeamId}/{$match->awayTeamId} played consecutive rounds (round {$roundIndex})"
                );
            }

            $currentOpponent = [];

            foreach ($round->matches as $match) {
                $currentOpponent[$match->homeTeamId] = $match->awayTeamId;
                $currentOpponent[$match->awayTeamId] = $match->homeTeamId;
            }

            foreach ($round->byeTeamIds as $byeTeamId) {
                unset($currentOpponent[$byeTeamId]);
                $lastOpponent[$byeTeamId] = null;
            }

            foreach ($currentOpponent as $teamId => $opponentId) {
                $lastOpponent[$teamId] = $opponentId;
            }
        }
    }

    public function test_partial_cycle_shorter_than_one_round_robin_is_hard_valid()
    {
        $homeVenueIdByTeamId = [1 => 101, 2 => 102, 3 => 103, 4 => 104];
        $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
        $venues = $this->venuesFor($homeVenueIdByTeamId);

        $candidate = (new RoundRobinConstructor())->construct($this->rounds(2, $venues), $teams, $venues);

        $this->assertNotNull($candidate);
        $this->assertCount(2, $candidate->rounds);
        $this->assertValidRoundRobin($candidate, array_keys($homeVenueIdByTeamId));
    }

    public function test_leftover_rounds_past_whole_cycles_stay_hard_valid_across_the_seam()
    {
        // The real shape this plan.md enhancement targets: association 2 /
        // schedule 6 - 4 teams, 4 distinct owned venues, 7 rounds (two full
        // cycles of 3 rounds plus a 1-round leftover).
        $homeVenueIdByTeamId = [1 => 101, 2 => 102, 3 => 103, 4 => 104];
        $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
        $venues = $this->venuesFor($homeVenueIdByTeamId);

        $candidate = (new RoundRobinConstructor())->construct($this->rounds(7, $venues), $teams, $venues);

        $this->assertNotNull($candidate);
        $this->assertCount(7, $candidate->rounds);
        $this->assertValidRoundRobinAllowingRematchesAcrossCycles($candidate);
    }

    public function test_declines_when_a_team_has_no_home_venue()
    {
        $teams = [
            new TeamInput(1, 'Team 1', 101),
            new TeamInput(2, 'Team 2', 102),
            new TeamInput(3, 'Team 3', null),
        ];
        $venues = [new VenueInput(101, 'Venue 101'), new VenueInput(102, 'Venue 102')];

        $this->assertFalse((new RoundRobinConstructor())->isEligible($teams, $venues));
        $this->assertNull((new RoundRobinConstructor())->construct($this->rounds(2, $venues), $teams, $venues));
    }

    public function test_declines_when_two_teams_share_a_home_venue()
    {
        $teams = [
            new TeamInput(1, 'Team 1', 500),
            new TeamInput(2, 'Team 2', 500),
            new TeamInput(3, 'Team 3', 600),
        ];
        $venues = [new VenueInput(500, 'Venue 500'), new VenueInput(600, 'Venue 600')];

        $this->assertFalse((new RoundRobinConstructor())->isEligible($teams, $venues));
        $this->assertNull((new RoundRobinConstructor())->construct($this->rounds(2, $venues), $teams, $venues));
    }

    public function test_declines_when_a_home_venue_is_not_in_the_active_venue_list()
    {
        $teams = [
            new TeamInput(1, 'Team 1', 101),
            new TeamInput(2, 'Team 2', 999), // not in $venues below.
            new TeamInput(3, 'Team 3', 103),
        ];
        $venues = [new VenueInput(101, 'Venue 101'), new VenueInput(103, 'Venue 103')];

        $this->assertFalse((new RoundRobinConstructor())->isEligible($teams, $venues));
        $this->assertNull((new RoundRobinConstructor())->construct($this->rounds(2, $venues), $teams, $venues));
    }

    public function test_declines_with_fewer_than_three_teams()
    {
        $teams = [new TeamInput(1, 'Team 1', 101), new TeamInput(2, 'Team 2', 102)];
        $venues = [new VenueInput(101, 'Venue 101'), new VenueInput(102, 'Venue 102')];

        $this->assertFalse((new RoundRobinConstructor())->isEligible($teams, $venues));
        $this->assertNull((new RoundRobinConstructor())->construct($this->rounds(2, $venues), $teams, $venues));
    }
}
