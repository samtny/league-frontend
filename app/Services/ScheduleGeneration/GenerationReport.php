<?php

namespace App\Services\ScheduleGeneration;

final class GenerationReport
{
    /**
     * @param string[] $hardViolations
     * @param array<string, string[]> $softViolationsByCriterion
     * @param array<int, array{key: string, label: string, score: float, weight: float, raw: float, epsilonUnit: float}> $softCriteriaScores every EVALUATED soft criterion's (i.e. present in GenerationConfig::$softCriteria) individual score, per-instance weight, config-independent raw penalty, and epsilon-constraint tolerance unit, in fixed order, regardless of whether it has any violation messages
     */
    public function __construct(
        public readonly bool $hardConstraintsSatisfied,
        public readonly array $hardViolations,
        public readonly array $softViolationsByCriterion,
        public readonly float $score,
        public readonly bool $degenerate,
        public readonly ?string $degenerateReason = null,
        public readonly array $softCriteriaScores = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'hard_constraints_satisfied' => $this->hardConstraintsSatisfied,
            'hard_violations' => $this->hardViolations,
            'soft_violations_by_criterion' => $this->softViolationsByCriterion,
            'score' => $this->score,
            'degenerate' => $this->degenerate,
            'degenerate_reason' => $this->degenerateReason,
            'soft_criteria_scores' => $this->softCriteriaScores,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['hard_constraints_satisfied'],
            $data['hard_violations'],
            $data['soft_violations_by_criterion'],
            $data['score'],
            $data['degenerate'],
            $data['degenerate_reason'] ?? null,
            $data['soft_criteria_scores'] ?? [],
        );
    }
}
