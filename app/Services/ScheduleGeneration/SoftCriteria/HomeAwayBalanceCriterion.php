<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class HomeAwayBalanceCriterion implements SoftCriterion
{
    /** @var array<int, int> */
    private array $homeCountByTeam;

    /** @var array<int, int> */
    private array $awayCountByTeam;

    private float $totalOver = 0.0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
        $this->homeCountByTeam = array_fill_keys(array_keys($context->activeTeamIds), 0);
        $this->awayCountByTeam = array_fill_keys(array_keys($context->activeTeamIds), 0);
    }

    public function key(): string
    {
        return 'home_away_balance';
    }

    public function label(): string
    {
        return 'Home/away count balance';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;

        $this->homeCountByTeam[$home] = ($this->homeCountByTeam[$home] ?? 0) + 1;
        $this->awayCountByTeam[$away] = ($this->awayCountByTeam[$away] ?? 0) + 1;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
    }

    public function finalize(): void
    {
        foreach (array_keys($this->context->activeTeamIds) as $teamId) {
            $diff = abs(($this->homeCountByTeam[$teamId] ?? 0) - ($this->awayCountByTeam[$teamId] ?? 0));
            $over = max(0, $diff - 1);

            if ($over > 0) {
                $this->totalOver += $over;
                $this->messages[] = "{$this->context->teamLabel($teamId)} has a home/away imbalance of {$diff}.";
            }
        }
    }

    public function penalty(GenerationConfig $config): float
    {
        $teamCount = count($this->context->activeTeamIds);

        return $this->weight($config) * ($this->totalOver / max(1, $teamCount));
    }

    public function weight(GenerationConfig $config): float
    {
        return $config->tierWeight($this->key());
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
