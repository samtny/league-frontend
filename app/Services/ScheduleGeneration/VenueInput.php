<?php

namespace App\Services\ScheduleGeneration;

use App\Venue;

final class VenueInput
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }

    public static function fromModel(Venue $venue): self
    {
        return new self((int) $venue->id, (string) $venue->name);
    }
}
