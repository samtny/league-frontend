<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\PairKey;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * Only compares a rematch against the IMMEDIATELY PRIOR meeting between that
 * same pair (like RepeatOpponentConsecutiveRoundsCriterion, one slot of
 * memory per pair, not full history) - a third+ meeting is judged only
 * against the second, not the first.
 */
final class RematchHomeAwayReversalCriterion implements SoftCriterion
{
    /** @var array<string, int> pair key => home team id at the most recent meeting */
    private array $lastHomeTeamByPair = [];

    private float $notReversedCount = 0.0;

    private int $rematchCount = 0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'rematch_home_away_reversal';
    }

    public function label(): string
    {
        return 'Rematch reverses home/away role';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $roundNumber = $roundIndex + 1;
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;
        $pairKey = PairKey::for($home, $away);
        $previousHome = $this->lastHomeTeamByPair[$pairKey] ?? null;

        if ($previousHome !== null) {
            $this->rematchCount++;

            if ($previousHome === $home) {
                $this->notReversedCount++;
                $this->messages[] = "{$this->context->teamLabel($home)} hosted {$this->context->teamLabel($away)} again in round {$roundNumber} without reversing home/away role from their last meeting.";
            }
        }

        $this->lastHomeTeamByPair[$pairKey] = $home;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
    }

    public function finalize(): void
    {
    }

    public function penalty(GenerationConfig $config): float
    {
        return $this->weight($config) * $this->rawPenalty();
    }

    public function weight(GenerationConfig $config): float
    {
        return $config->tierWeight($this->key());
    }

    public function rawPenalty(): float
    {
        return $this->notReversedCount / max(1, $this->rematchCount);
    }

    public function epsilonUnit(): float
    {
        return 1 / max(1, $this->rematchCount);
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
