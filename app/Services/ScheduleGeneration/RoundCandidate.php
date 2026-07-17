<?php

namespace App\Services\ScheduleGeneration;

final class RoundCandidate
{
    /**
     * @param MatchCandidate[] $matches
     * @param int[] $byeTeamIds
     */
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly array $matches,
        public readonly array $byeTeamIds,
        public readonly ?int $roundId = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'matches' => array_map(fn (MatchCandidate $match) => $match->toArray(), $this->matches),
            'bye_team_ids' => $this->byeTeamIds,
            'round_id' => $this->roundId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            new \DateTimeImmutable($data['date']),
            array_map(fn (array $match) => MatchCandidate::fromArray($match), $data['matches']),
            $data['bye_team_ids'],
            $data['round_id'] ?? null,
        );
    }
}
