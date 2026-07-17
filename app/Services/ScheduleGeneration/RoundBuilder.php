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

        $matches = $this->assignVenuesAndSides($pairs, $round->slots, $lastVenueByTeam, $homeCountByTeam, $awayCountByTeam, $homeVenueAppearancesByTeam);

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
     * @return MatchCandidate[]
     */
    private function assignVenuesAndSides(
        array $pairs,
        array $slots,
        array $lastVenueByTeam,
        array &$homeCountByTeam,
        array &$awayCountByTeam,
        array &$homeVenueAppearancesByTeam,
    ): array {
        $remainingVenues = array_values($this->rng->shuffle($slots));
        $pairs = $this->rng->shuffle($pairs);
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
                // fall back to the generic pool, preferring a venue that is
                // neither team's own home venue (never safe - whoever ends
                // up away there would violate H4) nor either team's last-
                // played venue; relaxing the last-played preference first if
                // no venue satisfies both, and only accepting a team's own
                // venue as an absolute last resort when literally no other
                // slot remains this round.
                $chosenIndex = null;
                $aLast = $lastVenueByTeam[$teamA->id] ?? null;
                $bLast = $lastVenueByTeam[$teamB->id] ?? null;

                foreach ($remainingVenues as $index => $candidateSlot) {
                    $isEitherTeamsOwnVenue = $candidateSlot->venueId === $teamA->homeVenueId || $candidateSlot->venueId === $teamB->homeVenueId;

                    if (! $isEitherTeamsOwnVenue && $candidateSlot->venueId !== $aLast && $candidateSlot->venueId !== $bLast) {
                        $chosenIndex = $index;
                        break;
                    }
                }

                if ($chosenIndex === null) {
                    foreach ($remainingVenues as $index => $candidateSlot) {
                        if ($candidateSlot->venueId !== $teamA->homeVenueId && $candidateSlot->venueId !== $teamB->homeVenueId) {
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
