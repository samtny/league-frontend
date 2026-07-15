<?php

namespace App\Services\ScheduleGeneration;

final class MatchCandidate
{
    public function __construct(
        public readonly int $venueId,
        public readonly string $venueName,
        public readonly int $homeTeamId,
        public readonly int $awayTeamId,
    ) {
    }

    public function toArray(): array
    {
        return [
            'venue_id' => $this->venueId,
            'venue_name' => $this->venueName,
            'home_team_id' => $this->homeTeamId,
            'away_team_id' => $this->awayTeamId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['venue_id'],
            $data['venue_name'],
            $data['home_team_id'],
            $data['away_team_id'],
        );
    }
}
