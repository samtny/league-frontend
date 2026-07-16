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
     */
    public function __construct(
        public readonly array $activeTeamIds,
        public readonly array $activeVenueIds,
        public readonly array $homeVenueIdByTeam,
        public readonly array $teamNameById,
        public readonly array $activeTeams,
        public readonly int $idealGap,
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

        foreach ($activeTeams as $team) {
            $homeVenueIdByTeam[$team->id] = $team->homeVenueId;
            $teamNameById[$team->id] = $team->name;
        }

        $activeTeamCount = count($activeTeams);

        return new self(
            activeTeamIds: array_flip(array_map(fn (TeamInput $t) => $t->id, $activeTeams)),
            activeVenueIds: array_flip(array_map(fn (VenueInput $v) => $v->id, $activeVenues)),
            homeVenueIdByTeam: $homeVenueIdByTeam,
            teamNameById: $teamNameById,
            activeTeams: $activeTeams,
            idealGap: $activeTeamCount > 0 ? (int) ceil($activeTeamCount / 2) : 0,
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
