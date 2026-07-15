<?php

namespace App\Services\ScheduleGeneration;

final class GenerationReport
{
    /**
     * @param string[] $hardViolations
     * @param array<string, string[]> $softViolationsByCriterion
     */
    public function __construct(
        public readonly bool $hardConstraintsSatisfied,
        public readonly array $hardViolations,
        public readonly array $softViolationsByCriterion,
        public readonly float $score,
        public readonly bool $degenerate,
        public readonly ?string $degenerateReason = null,
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
        );
    }
}
