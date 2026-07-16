<?php

namespace App\Services\ScheduleGeneration;

/**
 * SET ASIDE (dead code): no longer invoked by ScheduleGenerator, which is
 * greedy-only for now while Automatic assignment is reworked to populate
 * existing Matches instead of creating/deleting Rounds. Left in place, along
 * with its full test coverage in RoundRobinConstructorTest, in case it's
 * revisited later - see ScheduleGenerator::generate() for where it used to
 * be wired in.
 *
 * Deterministic classical round-robin construction for the exclusive-home-
 * venue case: every active team owns a distinct active venue. Produces a
 * "seed" ScheduleCandidate for ScheduleGenerator to score and (if it scores
 * lower than what the randomized-restart loop finds on its own) keep -
 * see plan.md, "Optimal Round-Robin Construction for the Exclusive-Home-
 * Venue Case". This class never returns an invalid candidate by design, but
 * makes no promises beyond that; the caller always re-verifies with
 * ScheduleScorer before trusting the result.
 *
 * Pairing uses the standard circle (polygon) method: team N-1 is fixed,
 * the rest rotate one step each round. Home/away is assigned by having
 * every team alternate from the previous round (a global "flip"), which
 * is only possible for the majority of rounds - at roughly every other
 * round the flip is structurally infeasible for two of that round's
 * matches (this is forced by the pairing, not a choice), and exactly one
 * team in each of those two matches must repeat its previous role instead
 * of flipping (a "break"). This was verified computationally (not derived
 * from a textbook formula - see plan.md "Exact HA orientation formula")
 * to reproduce the theoretical N-2 minimum-breaks bound for every even N
 * tested from 4 to 100, with at most a +/-1 home/away imbalance per team,
 * in O(N) rounds x O(N) work per round.
 */
final class RoundRobinConstructor
{
    /**
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    public function isEligible(array $activeTeams, array $activeVenues): bool
    {
        if (count($activeTeams) < 3) {
            return false;
        }

        $activeVenueIds = array_flip(array_map(fn (VenueInput $v) => $v->id, $activeVenues));
        $seenVenueIds = [];

        foreach ($activeTeams as $team) {
            if ($team->homeVenueId === null) {
                return false;
            }

            if (isset($seenVenueIds[$team->homeVenueId])) {
                return false;
            }

            $seenVenueIds[$team->homeVenueId] = true;

            if (! isset($activeVenueIds[$team->homeVenueId])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \DateTimeImmutable[] $roundDates
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    public function construct(array $roundDates, array $activeTeams, array $activeVenues): ?ScheduleCandidate
    {
        if (! $this->isEligible($activeTeams, $activeVenues)) {
            return null;
        }

        if (empty($roundDates)) {
            return new ScheduleCandidate([]);
        }

        $n = count($activeTeams);
        $isOdd = $n % 2 === 1;
        $slots = 0;

        // Slots 0..n-1 are the real teams, in input order (deterministic).
        // For an odd team count a phantom occupies the final slot; whoever
        // is drawn against the phantom in a given round takes that round's
        // bye instead of playing.
        $slotTeams = $activeTeams;

        if ($isOdd) {
            $slotTeams[] = null;
        }

        $slotCount = count($slotTeams);

        $venueLookup = [];
        foreach ($activeVenues as $venue) {
            $venueLookup[$venue->id] = $venue;
        }

        $cycle = $this->buildSingleCycle($slotCount);
        $roundCount = count($roundDates);

        $rounds = [];
        $pass = 0;

        while (count($rounds) < $roundCount) {
            $flip = $pass % 2 === 1;

            foreach ($cycle as $cycleRound) {
                if (count($rounds) >= $roundCount) {
                    break;
                }

                $date = $roundDates[count($rounds)];
                $rounds[] = $this->mapRound($date, $cycleRound, $slotTeams, $venueLookup, $flip);
            }

            $pass++;
        }

        return new ScheduleCandidate($rounds);
    }

    /**
     * Builds one full single round-robin cycle (N-1 rounds) for N slots:
     * the circle-method pairing plus a break-minimal home/away orientation.
     *
     * @return array<int, array{pairs: array<int, array{0: int, 1: int}>, role: array<int, int>}>
     */
    private function buildSingleCycle(int $slotCount): array
    {
        $fixed = $slotCount - 1;
        $m = $slotCount - 1;
        $cur = range(0, $slotCount - 2);
        $innerPairCount = intdiv($slotCount - 2, 2);

        // Round 0: alternate by slot parity. This always satisfies the
        // round-0 pairing (fixed vs slot 0, and slot i vs slot m-i for each
        // inner pair) because m is odd whenever N is even, so any two
        // slots that sum to an odd number always differ in parity.
        $role = [];
        for ($slot = 0; $slot < $slotCount; $slot++) {
            $role[$slot] = $slot % 2 === 0 ? 1 : 0;
        }

        $heldEver = array_fill(0, $slotCount, false);
        $rounds = [];

        for ($r = 0; $r < $m; $r++) {
            $pairs = [[$fixed, $cur[0]]];

            for ($i = 1; $i <= $innerPairCount; $i++) {
                $pairs[] = [$cur[$i], $cur[$m - $i]];
            }

            if ($r === 0) {
                $roundRole = $role;
            } else {
                $predicted = [];
                foreach ($role as $slot => $previousRole) {
                    $predicted[$slot] = 1 - $previousRole;
                }

                $roundRole = $predicted;

                foreach ($pairs as [$a, $b]) {
                    if ($predicted[$a] !== $predicted[$b]) {
                        continue;
                    }

                    // Flipping both would give this match two teams with the
                    // same role - structurally impossible, forced by the
                    // pairing, not a choice. One team must instead repeat
                    // its previous role (a break) while the other flips as
                    // predicted. Prefer to spend the break on whichever team
                    // hasn't already spent its (at most one) break; if both
                    // are still available, the higher slot spends it - this
                    // specific tie-break is what was verified computationally
                    // to keep the total at exactly N-2 breaks rather than
                    // cascading into extra breaks at later rounds.
                    $aAvailable = ! $heldEver[$a];
                    $bAvailable = ! $heldEver[$b];

                    if ($aAvailable && ! $bAvailable) {
                        $holder = $a;
                    } elseif ($bAvailable && ! $aAvailable) {
                        $holder = $b;
                    } else {
                        $holder = max($a, $b);
                    }

                    $other = $holder === $a ? $b : $a;
                    $roundRole[$holder] = $role[$holder];
                    $roundRole[$other] = $predicted[$other];
                    $heldEver[$holder] = true;
                }
            }

            $rounds[] = ['pairs' => $pairs, 'role' => $roundRole];
            $role = $roundRole;

            // Rotate right by one: the last element wraps to the front.
            $cur = array_merge([$cur[count($cur) - 1]], array_slice($cur, 0, -1));
        }

        return $rounds;
    }

    /**
     * @param array{pairs: array<int, array{0: int, 1: int}>, role: array<int, int>} $cycleRound
     * @param array<int, TeamInput|null> $slotTeams
     * @param array<int, VenueInput> $venueLookup
     */
    private function mapRound(
        \DateTimeImmutable $date,
        array $cycleRound,
        array $slotTeams,
        array $venueLookup,
        bool $flip,
    ): RoundCandidate {
        $matches = [];
        $byeTeamIds = [];

        foreach ($cycleRound['pairs'] as [$slotA, $slotB]) {
            $teamA = $slotTeams[$slotA];
            $teamB = $slotTeams[$slotB];

            if ($teamA === null || $teamB === null) {
                $byeTeamIds[] = ($teamA ?? $teamB)->id;

                continue;
            }

            $roleA = $cycleRound['role'][$slotA];

            if ($flip) {
                $roleA = 1 - $roleA;
            }

            [$home, $away] = $roleA === 1 ? [$teamA, $teamB] : [$teamB, $teamA];

            $venue = $venueLookup[$home->homeVenueId];
            $matches[] = new MatchCandidate($home->homeVenueId, $venue->name, $home->id, $away->id);
        }

        return new RoundCandidate($date, $matches, $byeTeamIds);
    }
}
