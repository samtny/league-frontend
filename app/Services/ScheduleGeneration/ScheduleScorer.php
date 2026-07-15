<?php

namespace App\Services\ScheduleGeneration;

/**
 * Pure, randomness-free scoring: replays a candidate round by round and
 * re-derives the same state RoundBuilder carries internally, so it can be
 * used both to score generator output and to unit-test hand-built
 * candidates directly.
 */
final class ScheduleScorer
{
    /**
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    public function score(ScheduleCandidate $candidate, array $activeTeams, array $activeVenues, GenerationConfig $config): GenerationReport
    {
        $activeTeamIds = array_flip(array_map(fn (TeamInput $t) => $t->id, $activeTeams));
        $activeVenueIds = array_flip(array_map(fn (VenueInput $v) => $v->id, $activeVenues));
        $homeVenueIdByTeam = [];
        $teamNameById = [];
        foreach ($activeTeams as $team) {
            $homeVenueIdByTeam[$team->id] = $team->homeVenueId;
            $teamNameById[$team->id] = $team->name;
        }

        // Falls back to "#id" for a team not in the active roster (e.g. the
        // inactive-team hard violation below, where no name is available).
        $teamLabel = fn (int $teamId): string => $teamNameById[$teamId] ?? "#{$teamId}";

        $hardViolations = [];

        $lastOpponentByTeam = [];
        $lastVenueByTeam = [];
        $lastMeetingRoundByPair = [];
        $matchesPlayedByTeam = array_fill_keys(array_keys($activeTeamIds), 0);
        $homeCountByTeam = array_fill_keys(array_keys($activeTeamIds), 0);
        $awayCountByTeam = array_fill_keys(array_keys($activeTeamIds), 0);
        $homeVenueAppearancesByTeam = array_fill_keys(array_keys($activeTeamIds), 0);

        $venueStreakPenalty = 0.0;
        $repeatPenalty = 0.0;
        $venueMessages = [];
        $repeatMessages = [];

        $activeTeamCount = count($activeTeams);
        $idealGap = $activeTeamCount > 0 ? (int) ceil($activeTeamCount / 2) : 0;

        foreach ($candidate->rounds as $roundIndex => $round) {
            $roundNumber = $roundIndex + 1;
            $seenThisRound = [];

            foreach ($round->matches as $match) {
                foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
                    if (! isset($activeTeamIds[$teamId])) {
                        $hardViolations[] = "Round {$roundNumber}: inactive or unknown team #{$teamId} was assigned a match.";
                    }

                    if (isset($seenThisRound[$teamId])) {
                        $hardViolations[] = "Round {$roundNumber}: {$teamLabel($teamId)} was assigned to more than one match.";
                    }

                    $seenThisRound[$teamId] = true;
                }

                if (! isset($activeVenueIds[$match->venueId])) {
                    $hardViolations[] = "Round {$roundNumber}: inactive or unknown venue #{$match->venueId} was used.";
                }

                $home = $match->homeTeamId;
                $away = $match->awayTeamId;

                if (($lastOpponentByTeam[$home] ?? null) === $away || ($lastOpponentByTeam[$away] ?? null) === $home) {
                    $hardViolations[] = "Round {$roundNumber}: {$teamLabel($home)} and {$teamLabel($away)} played each other in consecutive rounds.";
                }

                if (($homeVenueIdByTeam[$away] ?? null) === $match->venueId) {
                    $hardViolations[] = "Round {$roundNumber}: {$teamLabel($away)} was assigned away for a match held at their own home venue.";
                }

                if (($homeVenueIdByTeam[$home] ?? null) === $match->venueId) {
                    $homeVenueAppearancesByTeam[$home] = ($homeVenueAppearancesByTeam[$home] ?? 0) + 1;
                }

                foreach ([$home, $away] as $teamId) {
                    if (($lastVenueByTeam[$teamId] ?? null) === $match->venueId) {
                        $venueStreakPenalty += $config->weightVenue;
                        $venueMessages[] = "{$teamLabel($teamId)} played consecutive rounds at the same venue ({$match->venueName}) around round {$roundNumber}.";
                    }
                }

                $pairKey = PairKey::for($home, $away);

                if (isset($lastMeetingRoundByPair[$pairKey])) {
                    $gap = $roundIndex - $lastMeetingRoundByPair[$pairKey];
                    $shortfall = max(0, $idealGap - $gap);

                    if ($shortfall > 0) {
                        $repeatPenalty += $config->weightRepeat * $shortfall;
                        $repeatMessages[] = "{$teamLabel($home)} and {$teamLabel($away)} rematched after only {$gap} round(s) (ideally {$idealGap}+).";
                    }
                }

                $lastMeetingRoundByPair[$pairKey] = $roundIndex;
                $lastOpponentByTeam[$home] = $away;
                $lastOpponentByTeam[$away] = $home;
                $lastVenueByTeam[$home] = $match->venueId;
                $lastVenueByTeam[$away] = $match->venueId;
                $matchesPlayedByTeam[$home] = ($matchesPlayedByTeam[$home] ?? 0) + 1;
                $matchesPlayedByTeam[$away] = ($matchesPlayedByTeam[$away] ?? 0) + 1;
                $homeCountByTeam[$home] = ($homeCountByTeam[$home] ?? 0) + 1;
                $awayCountByTeam[$away] = ($awayCountByTeam[$away] ?? 0) + 1;
            }

            foreach ($round->byeTeamIds as $teamId) {
                if (isset($seenThisRound[$teamId])) {
                    $hardViolations[] = "Round {$roundNumber}: {$teamLabel($teamId)} was both byed and assigned a match.";
                }

                unset($lastOpponentByTeam[$teamId], $lastVenueByTeam[$teamId]);
            }
        }

        $equalityPenalty = 0.0;
        $equalityMessages = [];

        if (! empty($matchesPlayedByTeam)) {
            $min = min($matchesPlayedByTeam);
            $max = max($matchesPlayedByTeam);

            if ($max - $min > 0) {
                $equalityPenalty = $config->weightEquality * ($max - $min);
                $equalityMessages[] = "Matches played ranges from {$min} to {$max} across active teams.";
            }
        }

        $homeAwayPenalty = 0.0;
        $homeAwayMessages = [];

        foreach (array_keys($activeTeamIds) as $teamId) {
            $diff = abs(($homeCountByTeam[$teamId] ?? 0) - ($awayCountByTeam[$teamId] ?? 0));
            $over = max(0, $diff - 1);

            if ($over > 0) {
                $homeAwayPenalty += $config->weightHomeAway * $over;
                $homeAwayMessages[] = "{$teamLabel($teamId)} has a home/away imbalance of {$diff}.";
            }
        }

        $homeVenuePenalty = 0.0;
        $homeVenueMessages = [];

        foreach ($activeTeams as $team) {
            if ($team->homeVenueId === null) {
                continue;
            }

            $homeAppearances = $homeVenueAppearancesByTeam[$team->id] ?? 0;
            $otherAppearances = ($matchesPlayedByTeam[$team->id] ?? 0) - $homeAppearances;
            $diff = abs($homeAppearances - $otherAppearances);
            $over = max(0, $diff - 1);

            if ($over > 0) {
                $homeVenuePenalty += $config->weightHomeVenueBalance * $over;
                $played = $matchesPlayedByTeam[$team->id] ?? 0;
                $homeVenueMessages[] = "{$team->name} played at their own home venue {$homeAppearances} of {$played} matches (expected roughly half).";
            }
        }

        $softViolationsByCriterion = array_filter([
            'consecutive_venue' => $venueMessages,
            'equal_matches_played' => $equalityMessages,
            'opponent_recency' => $repeatMessages,
            'home_away_balance' => $homeAwayMessages,
            'home_venue_balance' => $homeVenueMessages,
        ]);

        return new GenerationReport(
            hardConstraintsSatisfied: empty($hardViolations),
            hardViolations: $hardViolations,
            softViolationsByCriterion: $softViolationsByCriterion,
            score: $venueStreakPenalty + $equalityPenalty + $repeatPenalty + $homeAwayPenalty + $homeVenuePenalty,
            degenerate: false,
        );
    }
}
