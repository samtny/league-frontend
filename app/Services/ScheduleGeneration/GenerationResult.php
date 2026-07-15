<?php

namespace App\Services\ScheduleGeneration;

final class GenerationResult
{
    public function __construct(
        public readonly ScheduleCandidate $candidate,
        public readonly GenerationReport $report,
        public readonly int $attemptsUsed,
        public readonly float $elapsedMs,
    ) {
    }
}
