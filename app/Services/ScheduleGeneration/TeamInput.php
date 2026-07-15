<?php

namespace App\Services\ScheduleGeneration;

use App\Team;

final class TeamInput
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $homeVenueId = null,
    ) {
    }

    public static function fromModel(Team $team): self
    {
        return new self(
            (int) $team->id,
            (string) $team->name,
            $team->venue_id !== null ? (int) $team->venue_id : null,
        );
    }
}
