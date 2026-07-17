<?php

namespace App\Services\ScheduleGeneration;

/**
 * Builds a single round's byes, pairings, and venue/home-away assignments,
 * given the running state carried over from prior rounds in this attempt.
 * Mutates that state in place so the caller can feed it straight into the
 * next round.
 */
final class RoundBuilder
{
    public function __construct(
        private readonly Rng $rng,
    ) {
    }

    /**
     * @param TeamInput[] $activeTeams
     * @param array<int, int> $byeCountByTeam team id => byes taken so far (mutated)
     * @param array<int, int|null> $lastVenueByTeam team id => venue played at in the immediately preceding round (mutated)
     * @param array<string, int> $lastMeetingRoundByPair pair key => round index of last meeting (mutated)
     * @param array<int, int> $homeCountByTeam (mutated)
     * @param array<int, int> $awayCountByTeam (mutated)
     * @param array<int, int> $homeVenueAppearancesByTeam team id => times played at their own home venue so far (mutated)
     */
    public function build(
        RoundInput $round,
        array $activeTeams,
        array &$byeCountByTeam,
        array &$lastVenueByTeam,
        array &$lastMeetingRoundByPair,
        array &$homeCountByTeam,
        array &$awayCountByTeam,
        array &$homeVenueAppearancesByTeam,
        int $roundIndex,
    ): RoundCandidate {
        $capacity = min(intdiv(count($activeTeams), 2), count($round->slots));
        $byeSlots = count($activeTeams) - 2 * $capacity;

        $shuffled = $this->rng->shuffle($activeTeams);
        usort($shuffled, fn (TeamInput $a, TeamInput $b) => $byeCountByTeam[$a->id] <=> $byeCountByTeam[$b->id]);

        $byeTeams = array_slice($shuffled, 0, $byeSlots);
        $playingTeams = array_slice($shuffled, $byeSlots);

        foreach ($byeTeams as $team) {
            $byeCountByTeam[$team->id]++;
        }

        $pairs = $this->pairTeams($playingTeams, $lastMeetingRoundByPair, $roundIndex);

        $matches = $this->assignVenuesAndSides($pairs, $round->slots, $lastVenueByTeam, $homeCountByTeam, $awayCountByTeam, $homeVenueAppearancesByTeam, $activeTeams);

        foreach ($matches as $match) {
            $lastVenueByTeam[$match->homeTeamId] = $match->venueId;
            $lastVenueByTeam[$match->awayTeamId] = $match->venueId;
            $lastMeetingRoundByPair[PairKey::for($match->homeTeamId, $match->awayTeamId)] = $roundIndex;
        }

        foreach ($byeTeams as $team) {
            $lastVenueByTeam[$team->id] = null;
        }

        return new RoundCandidate($round->date, $matches, array_map(fn (TeamInput $t) => $t->id, $byeTeams), $round->roundId);
    }

    /**
     * @param TeamInput[] $playingTeams
     * @param array<string, int> $lastMeetingRoundByPair
     * @return array<int, array{0: TeamInput, 1: TeamInput}>
     */
    private function pairTeams(
        array $playingTeams,
        array $lastMeetingRoundByPair,
        int $roundIndex,
    ): array {
        $remaining = $this->rng->shuffle($playingTeams);
        $pairs = [];

        while (count($remaining) > 0) {
            $team = array_shift($remaining);

            $candidates = [];

            foreach ($remaining as $index => $other) {
                $lastMet = $lastMeetingRoundByPair[PairKey::for($team->id, $other->id)] ?? null;
                $gap = OpponentGapCalculator::sinceLastMeeting($roundIndex, $lastMet) ?? PHP_INT_MAX;

                $candidates[] = ['index' => $index, 'gap' => $gap];
            }

            usort($candidates, fn (array $a, array $b) => $b['gap'] <=> $a['gap']);

            $chosen = $remaining[$candidates[0]['index']];
            array_splice($remaining, $candidates[0]['index'], 1);

            $pairs[] = [$team, $chosen];
        }

        return $pairs;
    }

    /**
     * @param array<int, array{0: TeamInput, 1: TeamInput}> $pairs
     * @param MatchSlotInput[] $slots
     * @param array<int, int|null> $lastVenueByTeam
     * @param array<int, int> $homeCountByTeam
     * @param array<int, int> $awayCountByTeam
     * @param array<int, int> $homeVenueAppearancesByTeam
     * @param TeamInput[] $activeTeams the full active roster (not just this round's playing teams) - a team sitting
     *   out this round on a bye still owns its venue, which is just as off-limits for another pair's neutral
     *   fallback as a playing team's is
     * @return MatchCandidate[]
     */
    private function assignVenuesAndSides(
        array $pairs,
        array $slots,
        array $lastVenueByTeam,
        array &$homeCountByTeam,
        array &$awayCountByTeam,
        array &$homeVenueAppearancesByTeam,
        array $activeTeams,
    ): array {
        $remainingVenues = array_values($this->rng->shuffle($slots));
        $pairs = $this->rng->shuffle($pairs);

        // Every EXCLUSIVELY-owned venue (exactly one active team, whether
        // playing, byed, or in another pair this round, calls it home) is
        // off-limits as a "neutral" choice below -
        // HomeTeamAtAnotherTeamsVenueConstraint rejects a team being marked
        // home at a venue owned by a different team, not just the two teams
        // actually playing in that match. A SHARED venue (2+ owners) is
        // deliberately left out of this set - it's still preferred as a
        // fallback if avoidable, but not protected as strictly, since two
        // co-owners can leave their own shared slot uncapturable by either
        // of them in a given round (see the constraint's own docblock).
        $ownerCountByVenueId = [];

        foreach ($activeTeams as $team) {
            if ($team->homeVenueId !== null) {
                $ownerCountByVenueId[$team->homeVenueId] = ($ownerCountByVenueId[$team->homeVenueId] ?? 0) + 1;
            }
        }

        $ownedVenueIds = array_filter($ownerCountByVenueId, fn (int $count) => $count === 1);

        // Pairs that MUST draw from the shared/neutral pool below - either
        // neither team has a currently-available own venue at all, or both
        // do but it's the same (shared) venue, which is always routed to
        // the pool anyway (see the collision check below) - are processed
        // BEFORE any pair that can self-supply its own distinct venue.
        // Self-supplying pairs never compete for the pool (their own venue
        // was never a genuinely neutral option for anyone else), so there's
        // nothing to gain by rushing them first; a pool-dependent pair,
        // though, has NO fallback if a self-supplying pair's claim happens
        // to be the only safe neutral venue left by the time it's its turn -
        // exactly the scenario that forced a shared-venue pair onto its own
        // (mutually forbidden) venue when this used to run in shuffle order.
        $remainingVenueIds = array_map(fn (MatchSlotInput $slot) => $slot->venueId, $remainingVenues);
        $needsPool = function (array $pair) use ($remainingVenueIds) {
            [$teamA, $teamB] = $pair;
            $aHas = $teamA->homeVenueId !== null && in_array($teamA->homeVenueId, $remainingVenueIds, true);
            $bHas = $teamB->homeVenueId !== null && in_array($teamB->homeVenueId, $remainingVenueIds, true);

            if ($aHas && $bHas) {
                return $teamA->homeVenueId === $teamB->homeVenueId;
            }

            return ! $aHas && ! $bHas;
        };
        usort($pairs, fn (array $a, array $b) => ($needsPool($b) ? 1 : 0) <=> ($needsPool($a) ? 1 : 0));

        $matches = [];

        foreach ($pairs as [$teamA, $teamB]) {
            $indexA = $teamA->homeVenueId !== null ? $this->findVenueIndex($remainingVenues, $teamA->homeVenueId) : null;
            $indexB = $teamB->homeVenueId !== null ? $this->findVenueIndex($remainingVenues, $teamB->homeVenueId) : null;

            if ($indexA !== null && $indexB !== null && $indexA === $indexB) {
                // These two opponents share the same home venue slot -
                // whichever one isn't "home" would have to be sent away to
                // their OWN home venue, which is always an H4 violation.
                // Route this match through the generic neutral-venue pool
                // below instead of forcing that.
                $indexA = null;
                $indexB = null;
            }

            $venueIndex = null;

            if ($indexA !== null && $indexB !== null) {
                // Both teams have an eligible home venue free this round
                // (possibly the same shared venue). Whoever just hosted last
                // round loses out first (this is what actually prevents a
                // team hosting several rounds running, since a fixed "lowest
                // cumulative count wins" rule has no memory of *recency* and
                // can otherwise keep re-selecting the same host); if neither
                // or both just hosted, whoever is more overdue for a home
                // game overall hosts; a genuine tie is broken by coin flip
                // rather than silently favoring whichever team happens to be
                // "A" in this pairing.
                $aJustHosted = HomeVenueMatch::isOwnVenue($teamA->homeVenueId, $lastVenueByTeam[$teamA->id] ?? null);
                $bJustHosted = HomeVenueMatch::isOwnVenue($teamB->homeVenueId, $lastVenueByTeam[$teamB->id] ?? null);
                $aAppearances = $homeVenueAppearancesByTeam[$teamA->id] ?? 0;
                $bAppearances = $homeVenueAppearancesByTeam[$teamB->id] ?? 0;

                if ($aJustHosted && ! $bJustHosted) {
                    $aHosts = false;
                } elseif ($bJustHosted && ! $aJustHosted) {
                    $aHosts = true;
                } elseif ($aAppearances < $bAppearances) {
                    $aHosts = true;
                } elseif ($bAppearances < $aAppearances) {
                    $aHosts = false;
                } else {
                    $aHosts = $this->rng->shuffle([true, false])[0];
                }

                [$home, $away, $venueIndex] = $aHosts ? [$teamA, $teamB, $indexA] : [$teamB, $teamA, $indexB];
            } elseif ($indexA !== null) {
                [$home, $away, $venueIndex] = [$teamA, $teamB, $indexA];
            } elseif ($indexB !== null) {
                [$home, $away, $venueIndex] = [$teamB, $teamA, $indexB];
            }

            if ($venueIndex !== null) {
                $venue = $remainingVenues[$venueIndex];
                array_splice($remainingVenues, $venueIndex, 1);
                $homeVenueAppearancesByTeam[$home->id] = ($homeVenueAppearancesByTeam[$home->id] ?? 0) + 1;
            } else {
                // Neither team has an eligible home venue free this round -
                // fall back to the generic pool. "Either of THIS pair's own
                // venue" is excluded in every tier except the absolute last
                // resort (never safe - whoever ends up away there violates
                // H4, including the shared-venue collision case routed here
                // above, where venue1 IS one of this pair's own venues even
                // though it's excluded from $ownedVenueIds). Decreasing
                // order of preference: (1) not this pair's own venue, not
                // owned by any OTHER active team, not either team's
                // last-played venue; (2) relax the last-played preference;
                // (3) if every remaining venue is owned by some active team,
                // at least still avoid this pair's own two venues (some
                // OTHER team's venue is now unavoidable and will trigger
                // HomeTeamAtAnotherTeamsVenueConstraint, but there's truly
                // nothing better left); (4) only as an absolute last resort,
                // whatever slot remains, including this pair's own.
                $chosenIndex = null;
                $aLast = $lastVenueByTeam[$teamA->id] ?? null;
                $bLast = $lastVenueByTeam[$teamB->id] ?? null;
                $isEitherTeamsOwnVenue = fn (MatchSlotInput $slot) => $slot->venueId === $teamA->homeVenueId || $slot->venueId === $teamB->homeVenueId;

                foreach ($remainingVenues as $index => $candidateSlot) {
                    if (! $isEitherTeamsOwnVenue($candidateSlot) && ! isset($ownedVenueIds[$candidateSlot->venueId]) && $candidateSlot->venueId !== $aLast && $candidateSlot->venueId !== $bLast) {
                        $chosenIndex = $index;
                        break;
                    }
                }

                if ($chosenIndex === null) {
                    foreach ($remainingVenues as $index => $candidateSlot) {
                        if (! $isEitherTeamsOwnVenue($candidateSlot) && ! isset($ownedVenueIds[$candidateSlot->venueId])) {
                            $chosenIndex = $index;
                            break;
                        }
                    }
                }

                if ($chosenIndex === null) {
                    foreach ($remainingVenues as $index => $candidateSlot) {
                        if (! $isEitherTeamsOwnVenue($candidateSlot)) {
                            $chosenIndex = $index;
                            break;
                        }
                    }
                }

                $chosenIndex ??= array_key_first($remainingVenues);
                $venue = $remainingVenues[$chosenIndex];
                array_splice($remainingVenues, $chosenIndex, 1);

                $aDiff = ($homeCountByTeam[$teamA->id] ?? 0) - ($awayCountByTeam[$teamA->id] ?? 0);
                $bDiff = ($homeCountByTeam[$teamB->id] ?? 0) - ($awayCountByTeam[$teamB->id] ?? 0);

                if ($aDiff < $bDiff) {
                    [$home, $away] = [$teamA, $teamB];
                } elseif ($bDiff < $aDiff) {
                    [$home, $away] = [$teamB, $teamA];
                } else {
                    [$home, $away] = $this->rng->shuffle([$teamA, $teamB]);
                }
            }

            $homeCountByTeam[$home->id] = ($homeCountByTeam[$home->id] ?? 0) + 1;
            $awayCountByTeam[$away->id] = ($awayCountByTeam[$away->id] ?? 0) + 1;

            $matches[] = new MatchCandidate($venue->venueId, $venue->venueName, $home->id, $away->id, $venue->matchId);
        }

        return $matches;
    }

    /**
     * @param MatchSlotInput[] $slots
     */
    private function findVenueIndex(array $slots, int $venueId): ?int
    {
        foreach ($slots as $index => $slot) {
            if ($slot->venueId === $venueId) {
                return $index;
            }
        }

        return null;
    }
}
