<?php

namespace App\Services\ScheduleGeneration;

/**
 * ExactSolver::solve()'s return value.
 */
final class ExactSolverResult
{
    public function __construct(
        public readonly ScheduleCandidate $candidate,
        public readonly GenerationReport $report,
        /**
         * True only if the entire ordering enumeration completed inside
         * the wall-clock budget - i.e. every ordering in the canonical-
         * cycle family (see ExactSolver's class docblock for what that
         * family does and doesn't cover) was either fully evaluated or
         * proven un-improvable by the pruning bound. False means the
         * budget expired first and $candidate/$report is merely the best
         * found so far - never worse than RoundRobinConstructor's own
         * seed, per ExactSolver's safety guarantee, but not certified
         * optimal.
         */
        public readonly bool $provenOptimal,
        /**
         * How many complete orderings were evaluated (DP run + pruning
         * check) before the search ended, whether by exhausting the
         * family or by timing out. Diagnostic only - useful for
         * confirming the enumeration rule matches the counts measured in
         * plan.md 1f (e.g. 4x6 -> 30, 4x10 -> 1,680).
         */
        public readonly int $orderingsExplored,
        public readonly float $elapsedMs,
    ) {}
}
