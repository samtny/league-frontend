<?php

namespace App\Services\ScheduleGeneration;

/**
 * Deterministic classical round-robin construction for the exclusive-home-
 * venue case (every active team owns a distinct active venue), plus one
 * narrow extension: exactly one active venue may be shared by exactly two
 * active teams (every other team's venue must still be exclusive). Used by
 * InitialSolutionBuilder as the construction phase's seed whenever eligible
 * - see plan.md, "Optimal Round-Robin Construction for the Exclusive-Home-
 * Venue Case", for the theory behind it. This class never returns an
 * invalid candidate by design, but makes no promises beyond that; the
 * caller always re-verifies with ScheduleScorer before trusting the result.
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
 *
 * Single-shared-venue-pair support: when two teams share a venue, they
 * can't simply be placed on ANY adjacent pair of slots - computationally
 * checking every adjacent pair (not just a handful) showed roughly half of
 * them still leave the pair simultaneously "home" (a real double-booking of
 * the shared venue) at some round, and even the ones that avoid that can
 * fail once a later multi-cycle pass flips every role (simultaneously
 * "away" pre-flip becomes simultaneously "home" post-flip). findSafeSlotPairs()
 * computes, directly from the actual built cycle, exactly which adjacent
 * slot pairs are safe in BOTH orientations - not a hardcoded formula, since
 * the safe set's shape (every other adjacent pair, starting at slot 0) was
 * only discovered by checking, not derived a priori, and re-deriving it at
 * runtime is cheap and keeps it correct if the tie-break logic above ever
 * changes. Their own head-to-head round is simply a normal match at the
 * shared venue (whichever of the two the canonical pattern makes "home"
 * that round hosts) - AwayTeamAtOwnVenueConstraint has a matching exception
 * for exactly this case. This does NOT generalize to more than one shared
 * venue, or to a venue shared by 3+ teams - isEligible() declines both.
 *
 * Slot assignment is randomized (via the injected Rng), NOT input order:
 * the break-minimal pattern this class produces is inherently unequal
 * (some slots are break-free, most carry exactly one break - see above),
 * so a fixed, deterministic team-to-slot mapping would hand the "lucky"
 * break-free slots to the same teams every single generation - a real
 * fairness bug, found via real usage, not theoretical. A shared-venue pair
 * (if any) is exempted from this randomization only insofar as which SAFE
 * slot pair they land on is itself randomly chosen (see assignTeamsToSlots())
 * - everyone else, and which of the two co-owners takes which of the two
 * chosen slots, is still randomized.
 */
final class RoundRobinConstructor
{
    public function __construct(
        private readonly Rng $rng,
    ) {
    }

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
        $teamIdsByVenue = [];

        foreach ($activeTeams as $team) {
            if ($team->homeVenueId === null) {
                return false;
            }

            if (! isset($activeVenueIds[$team->homeVenueId])) {
                return false;
            }

            $teamIdsByVenue[$team->homeVenueId][] = $team->id;
        }

        $sharedVenueCount = 0;

        foreach ($teamIdsByVenue as $teamIds) {
            if (count($teamIds) > 2) {
                return false;
            }

            if (count($teamIds) === 2) {
                $sharedVenueCount++;
            }
        }

        return $sharedVenueCount <= 1;
    }

    /**
     * The two teams sharing a venue, if any (isEligible() already
     * guarantees at most one such venue, with exactly 2 owners).
     *
     * @param TeamInput[] $activeTeams
     * @return array{0: TeamInput, 1: TeamInput}|null
     */
    private function findSharedVenuePair(array $activeTeams): ?array
    {
        $teamsByVenue = [];

        foreach ($activeTeams as $team) {
            $teamsByVenue[$team->homeVenueId][] = $team;
        }

        foreach ($teamsByVenue as $teams) {
            if (count($teams) === 2) {
                return $teams;
            }
        }

        return null;
    }

    /**
     * Which adjacent slot pairs never leave a shared-venue pair's roles
     * simultaneously equal (both home OR both away, since a later
     * multi-cycle pass flips everyone), checked directly against the
     * built cycle - see class docblock for why this is computed rather
     * than assumed. Only pairs entirely within the real-team range
     * (0..realTeamCount-2 for the lower slot) are candidates - the odd-team
     * phantom's slot is never eligible for the shared pair.
     *
     * @param array<int, array{pairs: array<int, array{0: int, 1: int}>, role: array<int, int>}> $cycle
     * @return array<int, array{0: int, 1: int}>
     */
    private function findSafeSlotPairs(array $cycle, int $realTeamCount): array
    {
        $safe = [];

        for ($slotA = 0; $slotA < $realTeamCount - 1; $slotA++) {
            $slotB = $slotA + 1;
            $everEqual = false;

            foreach ($cycle as $cycleRound) {
                if ($cycleRound['role'][$slotA] === $cycleRound['role'][$slotB]) {
                    $everEqual = true;

                    break;
                }
            }

            if (! $everEqual) {
                $safe[] = [$slotA, $slotB];
            }
        }

        return $safe;
    }

    /**
     * Slot assignment is randomized (see class docblock). With no shared
     * venue, every team is simply shuffled. With one, a safe slot pair is
     * chosen at random from findSafeSlotPairs(), the two co-owners are
     * randomly assigned between its two slots, and everyone else is
     * shuffled into the remaining slots.
     *
     * @param TeamInput[] $activeTeams
     * @param array<int, array{pairs: array<int, array{0: int, 1: int}>, role: array<int, int>}> $cycle
     * @return array<int, TeamInput>
     */
    private function assignTeamsToSlots(array $activeTeams, array $cycle): array
    {
        $sharedPair = $this->findSharedVenuePair($activeTeams);

        if ($sharedPair === null) {
            return $this->rng->shuffle($activeTeams);
        }

        [$first, $second] = $this->rng->shuffle($sharedPair);
        $others = array_values(array_filter($activeTeams, fn (TeamInput $t) => $t->id !== $first->id && $t->id !== $second->id));
        $shuffledOthers = $this->rng->shuffle($others);

        $safePairs = $this->findSafeSlotPairs($cycle, count($activeTeams));
        [$slotA, $slotB] = $this->rng->shuffle($safePairs)[0];

        $slots = array_fill(0, count($activeTeams), null);
        $slots[$slotA] = $first;
        $slots[$slotB] = $second;

        $otherIndex = 0;

        foreach ($slots as $slot => $team) {
            if ($team === null) {
                $slots[$slot] = $shuffledOthers[$otherIndex++];
            }
        }

        return $slots;
    }

    /**
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    public function construct(array $rounds, array $activeTeams, array $activeVenues): ?ScheduleCandidate
    {
        if (! $this->isEligible($activeTeams, $activeVenues)) {
            return null;
        }

        if (empty($rounds)) {
            return new ScheduleCandidate([]);
        }

        $n = count($activeTeams);
        $isOdd = $n % 2 === 1;
        $slotCount = $n + ($isOdd ? 1 : 0);

        // The cycle's abstract role-per-slot pattern depends only on
        // slotCount, never on which team occupies which slot - build it
        // first so a shared-venue pair's safe slots (see
        // findSafeSlotPairs()) can be computed before team assignment.
        $cycle = $this->buildSingleCycle($slotCount);

        // Slots 0..n-1 are the real teams, randomized (see class docblock,
        // and findSafeSlotPairs()/assignTeamsToSlots() for a shared-venue
        // pair's placement specifically). For an odd team count a phantom
        // occupies the final slot; whoever is drawn against the phantom in
        // a given round takes that round's bye instead of playing.
        $slotTeams = $this->assignTeamsToSlots($activeTeams, $cycle);

        if ($isOdd) {
            $slotTeams[] = null;
        }

        $venueLookup = [];
        foreach ($activeVenues as $venue) {
            $venueLookup[$venue->id] = $venue;
        }

        $roundCount = count($rounds);

        $roundCandidates = [];
        $pass = 0;

        while (count($roundCandidates) < $roundCount) {
            $flip = $pass % 2 === 1;

            foreach ($cycle as $cycleRound) {
                if (count($roundCandidates) >= $roundCount) {
                    break;
                }

                $round = $rounds[count($roundCandidates)];
                $roundCandidates[] = $this->mapRound($round, $cycleRound, $slotTeams, $venueLookup, $flip);
            }

            $pass++;
        }

        return new ScheduleCandidate($roundCandidates);
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
        RoundInput $round,
        array $cycleRound,
        array $slotTeams,
        array $venueLookup,
        bool $flip,
    ): RoundCandidate {
        $matchIdByVenueId = [];

        foreach ($round->slots as $slot) {
            $matchIdByVenueId[$slot->venueId] = $slot->matchId;
        }

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
            $matches[] = new MatchCandidate($home->homeVenueId, $venue->name, $home->id, $away->id, $matchIdByVenueId[$home->homeVenueId] ?? null);
        }

        return new RoundCandidate($round->date, $matches, $byeTeamIds, $round->roundId);
    }
}
