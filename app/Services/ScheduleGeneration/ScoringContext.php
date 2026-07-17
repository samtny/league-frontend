<?php

namespace App\Services\ScheduleGeneration;

/**
 * Read-only lookups shared by every hard constraint and soft criterion for a
 * single ScheduleScorer::score() call, built once up front so each rule
 * doesn't re-derive the same roster/venue indexes.
 */
final class ScoringContext
{
    /**
     * @param array<int, int> $activeTeamIds team id => index (flipped, for isset() checks)
     * @param array<int, int> $activeVenueIds venue id => index (flipped, for isset() checks)
     * @param array<int, int|null> $homeVenueIdByTeam team id => home venue id
     * @param array<int, string> $teamNameById team id => name
     * @param TeamInput[] $activeTeams
     * @param int $fullCycleGap number of rounds needed to face every other active team exactly once (a single
     *   round-robin cycle's length, activeTeamCount - 1) - the target gap FullCycleSpacingCriterion measures
     *   rematches against, so "ideal" means literally "every other team faced before a rematch," not merely
     *   "not too soon"
     * @param array<int, int[]> $ownerTeamIdsByVenue venue id => active team id(s) whose home venue this is
     *   (usually 0 or 1 team, but a venue may legitimately be shared by more than one active team) - used by
     *   HomeTeamAtAnotherTeamsVenueConstraint to check a match's home team against whoever actually owns its venue,
     *   not merely against the two teams playing in it
     */
    public function __construct(
        public readonly array $activeTeamIds,
        public readonly array $activeVenueIds,
        public readonly array $homeVenueIdByTeam,
        public readonly array $teamNameById,
        public readonly array $activeTeams,
        public readonly int $fullCycleGap,
        public readonly array $ownerTeamIdsByVenue,
    ) {
    }

    /**
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    public static function build(array $activeTeams, array $activeVenues): self
    {
        $homeVenueIdByTeam = [];
        $teamNameById = [];
        $ownerTeamIdsByVenue = [];

        foreach ($activeTeams as $team) {
            $homeVenueIdByTeam[$team->id] = $team->homeVenueId;
            $teamNameById[$team->id] = $team->name;

            if ($team->homeVenueId !== null) {
                $ownerTeamIdsByVenue[$team->homeVenueId][] = $team->id;
            }
        }

        $activeTeamCount = count($activeTeams);

        return new self(
            activeTeamIds: array_flip(array_map(fn (TeamInput $t) => $t->id, $activeTeams)),
            activeVenueIds: array_flip(array_map(fn (VenueInput $v) => $v->id, $activeVenues)),
            homeVenueIdByTeam: $homeVenueIdByTeam,
            teamNameById: $teamNameById,
            activeTeams: $activeTeams,
            fullCycleGap: max(0, $activeTeamCount - 1),
            ownerTeamIdsByVenue: $ownerTeamIdsByVenue,
        );
    }

    /**
     * Falls back to "#id" for a team not in the active roster (e.g. the
     * inactive-team hard violation, where no name is available).
     */
    public function teamLabel(int $teamId): string
    {
        return $this->teamNameById[$teamId] ?? "#{$teamId}";
    }
}
