<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class EqualMatchesPlayedCriterion implements SoftCriterion
{
    /** @var array<int, int> team id => matches played so far */
    private array $matchesPlayedByTeam;

    private float $spread = 0.0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
        $this->matchesPlayedByTeam = array_fill_keys(array_keys($context->activeTeamIds), 0);
    }

    public function key(): string
    {
        return 'equal_matches_played';
    }

    public function label(): string
    {
        return 'Equal matches played across teams';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;

        $this->matchesPlayedByTeam[$home] = ($this->matchesPlayedByTeam[$home] ?? 0) + 1;
        $this->matchesPlayedByTeam[$away] = ($this->matchesPlayedByTeam[$away] ?? 0) + 1;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
    }

    public function finalize(): void
    {
        if (empty($this->matchesPlayedByTeam)) {
            return;
        }

        $min = min($this->matchesPlayedByTeam);
        $max = max($this->matchesPlayedByTeam);

        if ($max - $min > 0) {
            $this->spread = $max - $min;
            $this->messages[] = "Matches played ranges from {$min} to {$max} across active teams.";
        }
    }

    public function penalty(GenerationConfig $config): float
    {
        return $config->weightEquality * $this->spread;
    }

    public function weight(GenerationConfig $config): float
    {
        return $config->weightEquality;
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
