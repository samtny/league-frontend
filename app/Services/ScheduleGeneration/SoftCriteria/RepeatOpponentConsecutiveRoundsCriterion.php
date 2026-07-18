<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * Previously a hard constraint (RoundBuilder actively avoided it and
 * ScheduleScorer rejected any candidate that had one) - a team facing the
 * same opponent in consecutive rounds is no longer rejected outright, just
 * penalized. Flat per-occurrence penalty, no repeat-offense surcharge.
 */
final class RepeatOpponentConsecutiveRoundsCriterion implements SoftCriterion
{
    use RecordsRoundViolations;

    /** @var array<int, int|null> team id => opponent in the immediately preceding round */
    private array $lastOpponentByTeam = [];

    private float $repeatCount = 0.0;

    private int $matchCount = 0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'repeat_opponent_consecutive_rounds';
    }

    public function label(): string
    {
        return 'Same opponent in consecutive rounds';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;

        if (($this->lastOpponentByTeam[$home] ?? null) === $away || ($this->lastOpponentByTeam[$away] ?? null) === $home) {
            $roundNumber = $roundIndex + 1;
            $this->repeatCount++;
            $this->messages[] = "{$this->context->teamLabel($home)} and {$this->context->teamLabel($away)} played each other in consecutive rounds around round {$roundNumber}.";
            $this->flagRoundViolation($roundIndex, $home, $away);
        }

        $this->lastOpponentByTeam[$home] = $away;
        $this->lastOpponentByTeam[$away] = $home;
        $this->matchCount++;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
        unset($this->lastOpponentByTeam[$teamId]);
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
        return $this->repeatCount / max(1, $this->matchCount);
    }

    public function epsilonUnit(): float
    {
        return 1 / max(1, $this->matchCount);
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
