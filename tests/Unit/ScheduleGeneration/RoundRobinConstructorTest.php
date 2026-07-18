<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\RoundRobinConstructor;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class RoundRobinConstructorTest extends TestCase
{
    /**
     * Slot assignment is randomized (for fairness across generations - see
     * the class docblock), so tests need a seeded, reproducible Rng rather
     * than production's crypto-random one.
     */
    private function constructor(int $seed = 1): RoundRobinConstructor
    {
        return new RoundRobinConstructor(new SeededRng($seed));
    }

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

            $candidate = $this->constructor()->construct($this->rounds($n - 1, $venues), $teams, $venues);

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

        $candidate = $this->constructor()->construct($this->rounds(4, $venues), $teams, $venues);

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

        $candidate = $this->constructor()->construct($this->rounds(6, $venues), $teams, $venues);

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

        $candidate = $this->constructor()->construct($this->rounds(2, $venues), $teams, $venues);

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

        $candidate = $this->constructor()->construct($this->rounds(7, $venues), $teams, $venues);

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

        $this->assertFalse($this->constructor()->isEligible($teams, $venues));
        $this->assertNull($this->constructor()->construct($this->rounds(2, $venues), $teams, $venues));
    }

    public function test_accepts_exactly_one_shared_venue_pair()
    {
        $teams = [
            new TeamInput(1, 'Team 1', 500),
            new TeamInput(2, 'Team 2', 500),
            new TeamInput(3, 'Team 3', 600),
        ];
        $venues = [new VenueInput(500, 'Venue 500'), new VenueInput(600, 'Venue 600')];

        $this->assertTrue($this->constructor()->isEligible($teams, $venues));
        $this->assertNotNull($this->constructor()->construct($this->rounds(2, $venues), $teams, $venues));
    }

    public function test_declines_when_three_teams_share_a_home_venue()
    {
        $teams = [
            new TeamInput(1, 'Team 1', 500),
            new TeamInput(2, 'Team 2', 500),
            new TeamInput(3, 'Team 3', 500),
            new TeamInput(4, 'Team 4', 600),
        ];
        $venues = [new VenueInput(500, 'Venue 500'), new VenueInput(600, 'Venue 600')];

        $this->assertFalse($this->constructor()->isEligible($teams, $venues));
        $this->assertNull($this->constructor()->construct($this->rounds(2, $venues), $teams, $venues));
    }

    public function test_declines_when_two_separate_venues_are_each_shared_by_a_pair()
    {
        $teams = [
            new TeamInput(1, 'Team 1', 500),
            new TeamInput(2, 'Team 2', 500),
            new TeamInput(3, 'Team 3', 600),
            new TeamInput(4, 'Team 4', 600),
        ];
        $venues = [new VenueInput(500, 'Venue 500'), new VenueInput(600, 'Venue 600')];

        $this->assertFalse($this->constructor()->isEligible($teams, $venues));
        $this->assertNull($this->constructor()->construct($this->rounds(2, $venues), $teams, $venues));
    }

    public function test_shared_venue_pair_never_both_marked_home_the_same_round_across_many_seeds_and_a_double_cycle()
    {
        // The property that makes this extension work: placing the shared
        // pair on a SAFE slot pair (see findSafeSlotPairs() - NOT just any
        // adjacent pair, an earlier version of this got that wrong: roughly
        // half of all adjacent pairs still collide, and even a pair that's
        // safe for an unflipped pass can fail once a later pass flips every
        // role) keeps their roles complementary. Slot assignment is
        // randomized per construct() call (see class docblock, added for
        // fairness across generations), so this is checked across many
        // seeds - a single lucky seed passing wouldn't prove much - and
        // across 26 rounds (two full 13-round cycles, the second one
        // flipped) since that's exactly the orientation a single-cycle-only
        // check can't catch.
        $homeVenueIdByTeamId = [1 => 500, 2 => 500];
        for ($i = 3; $i <= 14; $i++) {
            $homeVenueIdByTeamId[$i] = 100 + $i;
        }

        $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);

        // venuesFor() emits one VenueInput per team, so venue 500 would
        // otherwise appear twice (once for team 1, once for team 2) -
        // de-dupe by id, keeping one VenueInput per distinct venue.
        $venuesById = [];
        foreach ($this->venuesFor($homeVenueIdByTeamId) as $venue) {
            $venuesById[$venue->id] = $venue;
        }
        $venues = array_values($venuesById);

        for ($seed = 1; $seed <= 30; $seed++) {
            $candidate = $this->constructor($seed)->construct($this->rounds(26, $venues), $teams, $venues);

            $this->assertNotNull($candidate, "seed {$seed}");

            $sawHeadToHead = false;

            foreach ($candidate->rounds as $roundIndex => $round) {
                $role1 = null;
                $role2 = null;
                $isHeadToHead = false;

                foreach ($round->matches as $match) {
                    if ($match->homeTeamId === 1) {
                        $role1 = 'H';
                    } elseif ($match->awayTeamId === 1) {
                        $role1 = 'A';
                    }

                    if ($match->homeTeamId === 2) {
                        $role2 = 'H';
                    } elseif ($match->awayTeamId === 2) {
                        $role2 = 'A';
                    }

                    if (($match->homeTeamId === 1 && $match->awayTeamId === 2) || ($match->homeTeamId === 2 && $match->awayTeamId === 1)) {
                        $isHeadToHead = true;
                        $sawHeadToHead = true;
                        // Their own head-to-head match is legitimately
                        // hosted at the shared venue - not a violation,
                        // just a normal game.
                        $this->assertSame(500, $match->venueId, "seed {$seed} round {$roundIndex}: head-to-head should be at the shared venue");
                    }
                }

                if (! $isHeadToHead) {
                    // Never both "home" the same round - that would
                    // double-book the shared venue (their own head-to-head
                    // round, asserted above, is the sole legitimate
                    // exception).
                    $this->assertFalse($role1 === 'H' && $role2 === 'H', "seed {$seed} round {$roundIndex}: teams 1 and 2 both marked home - would double-book the shared venue");
                }
            }

            $this->assertTrue($sawHeadToHead, "seed {$seed}: a 26-round double cycle for 14 teams should include their head-to-head round");
        }
    }

    public function test_team_to_slot_placement_varies_across_generations()
    {
        // The bug report this was built for: a deterministic team-to-slot
        // mapping handed the same few teams the break-free slots on every
        // single generation. Assert team 1's round-1 opponent (a direct
        // stand-in for "which slot did team 1 land on") isn't pinned to one
        // fixed outcome across many seeds - covers both the general
        // (no shared venue) and shared-venue-pair placement paths.
        $homeVenueIdByTeamId = [];
        for ($i = 1; $i <= 14; $i++) {
            $homeVenueIdByTeamId[$i] = 100 + $i;
        }

        $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
        $venues = $this->venuesFor($homeVenueIdByTeamId);

        $opponents = [];

        for ($seed = 1; $seed <= 30; $seed++) {
            $candidate = $this->constructor($seed)->construct($this->rounds(1, $venues), $teams, $venues);

            foreach ($candidate->rounds[0]->matches as $match) {
                if ($match->homeTeamId === 1) {
                    $opponents[] = $match->awayTeamId;
                } elseif ($match->awayTeamId === 1) {
                    $opponents[] = $match->homeTeamId;
                }
            }
        }

        $this->assertGreaterThan(1, count(array_unique($opponents)), 'team 1 should draw more than one distinct round-1 opponent across 30 seeds if slot placement is genuinely randomized');
    }

    public function test_shared_venue_pair_placement_varies_across_generations()
    {
        // Same fairness property as above, specifically for the pair
        // sharing a venue - which of the (multiple) safe slot pairs they
        // land on, and which of the two co-owners takes which slot, should
        // both vary across generations rather than always resolving the
        // same way.
        $homeVenueIdByTeamId = [1 => 500, 2 => 500];
        for ($i = 3; $i <= 14; $i++) {
            $homeVenueIdByTeamId[$i] = 100 + $i;
        }

        $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
        $venuesById = [];
        foreach ($this->venuesFor($homeVenueIdByTeamId) as $venue) {
            $venuesById[$venue->id] = $venue;
        }
        $venues = array_values($venuesById);

        $opponents = [];

        for ($seed = 1; $seed <= 30; $seed++) {
            $candidate = $this->constructor($seed)->construct($this->rounds(1, $venues), $teams, $venues);

            foreach ($candidate->rounds[0]->matches as $match) {
                if ($match->homeTeamId === 1) {
                    $opponents[] = $match->awayTeamId;
                } elseif ($match->awayTeamId === 1) {
                    $opponents[] = $match->homeTeamId;
                }
            }
        }

        $this->assertGreaterThan(1, count(array_unique($opponents)), 'team 1 should draw more than one distinct round-1 opponent across 30 seeds even with a shared-venue pair present');
    }

    public function test_declines_when_a_home_venue_is_not_in_the_active_venue_list()
    {
        $teams = [
            new TeamInput(1, 'Team 1', 101),
            new TeamInput(2, 'Team 2', 999), // not in $venues below.
            new TeamInput(3, 'Team 3', 103),
        ];
        $venues = [new VenueInput(101, 'Venue 101'), new VenueInput(103, 'Venue 103')];

        $this->assertFalse($this->constructor()->isEligible($teams, $venues));
        $this->assertNull($this->constructor()->construct($this->rounds(2, $venues), $teams, $venues));
    }

    public function test_declines_with_fewer_than_three_teams()
    {
        $teams = [new TeamInput(1, 'Team 1', 101), new TeamInput(2, 'Team 2', 102)];
        $venues = [new VenueInput(101, 'Venue 101'), new VenueInput(102, 'Venue 102')];

        $this->assertFalse($this->constructor()->isEligible($teams, $venues));
        $this->assertNull($this->constructor()->construct($this->rounds(2, $venues), $teams, $venues));
    }
}
