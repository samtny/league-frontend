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
     * @param VenueInput[] $activeVenues
     * @param array<int, int> $byeCountByTeam team id => byes taken so far (mutated)
     * @param array<int, int|null> $lastOpponentByTeam team id => opponent in the immediately preceding round (mutated)
     * @param array<int, int|null> $lastVenueByTeam team id => venue played at in the immediately preceding round (mutated)
     * @param array<string, int> $lastMeetingRoundByPair pair key => round index of last meeting (mutated)
     * @param array<int, int> $homeCountByTeam (mutated)
     * @param array<int, int> $awayCountByTeam (mutated)
     * @param array<int, int> $homeVenueAppearancesByTeam team id => times played at their own home venue so far (mutated)
     *
     * @throws UnableToBuildRoundException
     */
    public function build(
        \DateTimeImmutable $date,
        array $activeTeams,
        array $activeVenues,
        array &$byeCountByTeam,
        array &$lastOpponentByTeam,
        array &$lastVenueByTeam,
        array &$lastMeetingRoundByPair,
        array &$homeCountByTeam,
        array &$awayCountByTeam,
        array &$homeVenueAppearancesByTeam,
        int $roundIndex,
        bool $enforceNoConsecutiveOpponent,
    ): RoundCandidate {
        $capacity = min(intdiv(count($activeTeams), 2), count($activeVenues));
        $byeSlots = count($activeTeams) - 2 * $capacity;

        $shuffled = $this->rng->shuffle($activeTeams);
        usort($shuffled, fn (TeamInput $a, TeamInput $b) => $byeCountByTeam[$a->id] <=> $byeCountByTeam[$b->id]);

        $byeTeams = array_slice($shuffled, 0, $byeSlots);
        $playingTeams = array_slice($shuffled, $byeSlots);

        foreach ($byeTeams as $team) {
            $byeCountByTeam[$team->id]++;
        }

        $pairs = $this->pairTeams($playingTeams, $lastOpponentByTeam, $lastMeetingRoundByPair, $roundIndex, $enforceNoConsecutiveOpponent);

        $matches = $this->assignVenuesAndSides($pairs, $activeVenues, $lastVenueByTeam, $homeCountByTeam, $awayCountByTeam, $homeVenueAppearancesByTeam);

        foreach ($matches as $match) {
            $lastOpponentByTeam[$match->homeTeamId] = $match->awayTeamId;
            $lastOpponentByTeam[$match->awayTeamId] = $match->homeTeamId;
            $lastVenueByTeam[$match->homeTeamId] = $match->venueId;
            $lastVenueByTeam[$match->awayTeamId] = $match->venueId;
            $lastMeetingRoundByPair[PairKey::for($match->homeTeamId, $match->awayTeamId)] = $roundIndex;
        }

        foreach ($byeTeams as $team) {
            $lastOpponentByTeam[$team->id] = null;
            $lastVenueByTeam[$team->id] = null;
        }

        return new RoundCandidate($date, $matches, array_map(fn (TeamInput $t) => $t->id, $byeTeams));
    }

    /**
     * @param TeamInput[] $playingTeams
     * @param array<int, int|null> $lastOpponentByTeam
     * @param array<string, int> $lastMeetingRoundByPair
     * @return array<int, array{0: TeamInput, 1: TeamInput}>
     *
     * @throws UnableToBuildRoundException
     */
    private function pairTeams(
        array $playingTeams,
        array $lastOpponentByTeam,
        array $lastMeetingRoundByPair,
        int $roundIndex,
        bool $enforceNoConsecutiveOpponent,
    ): array {
        $remaining = $this->rng->shuffle($playingTeams);
        $pairs = [];

        while (count($remaining) > 0) {
            $team = array_shift($remaining);

            $candidates = [];

            foreach ($remaining as $index => $other) {
                if ($enforceNoConsecutiveOpponent && ($lastOpponentByTeam[$team->id] ?? null) === $other->id) {
                    continue;
                }

                $lastMet = $lastMeetingRoundByPair[PairKey::for($team->id, $other->id)] ?? null;
                $gap = $lastMet === null ? PHP_INT_MAX : $roundIndex - $lastMet;

                $candidates[] = ['index' => $index, 'gap' => $gap];
            }

            if (empty($candidates)) {
                throw new UnableToBuildRoundException(
                    "No valid opponent for team #{$team->id} without repeating the immediately preceding matchup."
                );
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
     * @param VenueInput[] $activeVenues
     * @param array<int, int|null> $lastVenueByTeam
     * @param array<int, int> $homeCountByTeam
     * @param array<int, int> $awayCountByTeam
     * @param array<int, int> $homeVenueAppearancesByTeam
     * @return MatchCandidate[]
     */
    private function assignVenuesAndSides(
        array $pairs,
        array $activeVenues,
        array $lastVenueByTeam,
        array &$homeCountByTeam,
        array &$awayCountByTeam,
        array &$homeVenueAppearancesByTeam,
    ): array {
        $remainingVenues = array_values($this->rng->shuffle($activeVenues));
        $pairs = $this->rng->shuffle($pairs);
        $matches = [];

        foreach ($pairs as [$teamA, $teamB]) {
            $indexA = $teamA->homeVenueId !== null ? $this->findVenueIndex($remainingVenues, $teamA->homeVenueId) : null;
            $indexB = $teamB->homeVenueId !== null ? $this->findVenueIndex($remainingVenues, $teamB->homeVenueId) : null;

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
                $aJustHosted = ($lastVenueByTeam[$teamA->id] ?? null) === $teamA->homeVenueId;
                $bJustHosted = ($lastVenueByTeam[$teamB->id] ?? null) === $teamB->homeVenueId;
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
                // fall back to the generic pool, avoiding each team's own
                // last-played venue, and balance by the home/away label count.
                $chosenIndex = null;

                foreach ($remainingVenues as $index => $candidate) {
                    $aLast = $lastVenueByTeam[$teamA->id] ?? null;
                    $bLast = $lastVenueByTeam[$teamB->id] ?? null;

                    if ($candidate->id !== $aLast && $candidate->id !== $bLast) {
                        $chosenIndex = $index;
                        break;
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

            $matches[] = new MatchCandidate($venue->id, $venue->name, $home->id, $away->id);
        }

        return $matches;
    }

    /**
     * @param VenueInput[] $venues
     */
    private function findVenueIndex(array $venues, int $venueId): ?int
    {
        foreach ($venues as $index => $venue) {
            if ($venue->id === $venueId) {
                return $index;
            }
        }

        return null;
    }
}
