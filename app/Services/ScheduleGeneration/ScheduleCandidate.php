<?php

namespace App\Services\ScheduleGeneration;

final class ScheduleCandidate
{
    /**
     * @param RoundCandidate[] $rounds
     */
    public function __construct(
        public readonly array $rounds,
    ) {
    }

    public function toArray(): array
    {
        return [
            'rounds' => array_map(fn (RoundCandidate $round) => $round->toArray(), $this->rounds),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            array_map(fn (array $round) => RoundCandidate::fromArray($round), $data['rounds']),
        );
    }
}
