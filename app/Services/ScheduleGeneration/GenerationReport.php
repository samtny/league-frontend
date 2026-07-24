<?php

namespace App\Services\ScheduleGeneration;

final class GenerationReport
{
    /**
     * @param  string[]  $hardViolations
     * @param  array<string, string[]>  $softViolationsByCriterion
     * @param  array<int, array{key: string, label: string, score: float, weight: float, raw: float, epsilonUnit: float}>  $softCriteriaScores  every EVALUATED soft criterion's (i.e. present in GenerationConfig::$softCriteria) individual score, per-instance weight, config-independent raw penalty, and epsilon-constraint tolerance unit, in fixed order, regardless of whether it has any violation messages
     * @param  array<int, array<int, string[]>>  $softTeamViolationsByRound  round index => team id => labels of soft criteria that flagged that team in that round. Only criteria with single-round attribution contribute here (see SoftCriterion::roundViolations()) - whole-schedule aggregates never appear.
     * @param  ?string  $strategy  GenerationStrategy::$value of whichever strategy ScheduleGenerator actually ran to
     *                             produce this candidate - null only for a report built directly by ScheduleScorer outside ScheduleGenerator
     *                             (e.g. hand-scored test fixtures), which has no strategy to attribute.
     * @param  string[]  $balancedOpponentsViolations  BalancedOpponentMeetingsConstraint's violation messages against
     *                                                 this candidate, populated ONLY when the strategy that produced it ran with
     *                                                 GenerationConfig::$enforceBalancedOpponents off (currently just GenerationStrategy::Greedy) - see
     *                                                 plan.md §4. Always empty when the constraint was enforced as a hard gate, since a violating candidate
     *                                                 could never have been returned in that case. This is a soft, informational warning (decision 2.6), not a
     *                                                 hard violation - it is deliberately kept separate from $hardViolations.
     * @param  ?bool  $provenOptimal  GenerationStrategy::Exact's ExactSolverResult::$provenOptimal, carried through so the review
     *                                screen can state plainly whether this candidate is proven optimal or merely best-found within
     *                                the time budget (plan.md §6/§7) - null for every OTHER strategy, which makes no optimality claim
     *                                at all, not even an implicit one; true/false is meaningful ONLY when $strategy is 'exact'.
     * @param  ?string  $strategyWarning  A soft-failure warning (decision 2.6) about the CHOICE of strategy itself, as opposed to
     *                                    $balancedOpponentsViolations (a warning about what the result contains). Currently only set
     *                                    when GenerationStrategy::Exact was requested but the venue ownership data made it ineligible,
     *                                    so ScheduleGenerator degraded to Greedy instead (see ScheduleGenerator::degradeExactToGreedy())
     *                                    - null whenever the requested strategy ran as requested.
     */
    public function __construct(
        public readonly bool $hardConstraintsSatisfied,
        public readonly array $hardViolations,
        public readonly array $softViolationsByCriterion,
        public readonly float $score,
        public readonly bool $degenerate,
        public readonly ?string $degenerateReason = null,
        public readonly array $softCriteriaScores = [],
        public readonly array $softTeamViolationsByRound = [],
        public readonly ?string $strategy = null,
        public readonly array $balancedOpponentsViolations = [],
        public readonly ?bool $provenOptimal = null,
        public readonly ?string $strategyWarning = null,
    ) {}

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
            'soft_team_violations_by_round' => $this->softTeamViolationsByRound,
            'strategy' => $this->strategy,
            'balanced_opponents_violations' => $this->balancedOpponentsViolations,
            'proven_optimal' => $this->provenOptimal,
            'strategy_warning' => $this->strategyWarning,
        ];
    }

    /**
     * Returns a copy of this report carrying which strategy produced it (and,
     * when relevant, the balanced-opponent-meetings warning that strategy's
     * relaxed hard constraint allows through, and/or GenerationStrategy::
     * Exact's proven-optimal-vs-best-found flag) - see the constructor's
     * docblock for all three fields. ScheduleGenerator calls this after the
     * fact on reports it receives from ScheduleScorer/EpsilonConstraintOptimizer/
     * ExactSolver, none of which know which strategy is running.
     *
     * @param  string[]  $balancedOpponentsViolations
     */
    public function withStrategyMetadata(GenerationStrategy $strategy, array $balancedOpponentsViolations = [], ?bool $provenOptimal = null): self
    {
        return new self(
            hardConstraintsSatisfied: $this->hardConstraintsSatisfied,
            hardViolations: $this->hardViolations,
            softViolationsByCriterion: $this->softViolationsByCriterion,
            score: $this->score,
            degenerate: $this->degenerate,
            degenerateReason: $this->degenerateReason,
            softCriteriaScores: $this->softCriteriaScores,
            softTeamViolationsByRound: $this->softTeamViolationsByRound,
            strategy: $strategy->value,
            balancedOpponentsViolations: $balancedOpponentsViolations,
            provenOptimal: $provenOptimal,
            strategyWarning: $this->strategyWarning,
        );
    }

    /**
     * Returns a copy of this report carrying a strategy-choice warning (see
     * the constructor's docblock for $strategyWarning) - used by
     * ScheduleGenerator::degradeExactToGreedy() to stamp the Greedy report
     * it falls back to with a note that Exact was requested first and
     * couldn't run, without disturbing anything else that report already
     * carries (e.g. Greedy's own $balancedOpponentsViolations).
     */
    public function withStrategyWarning(string $strategyWarning): self
    {
        return new self(
            hardConstraintsSatisfied: $this->hardConstraintsSatisfied,
            hardViolations: $this->hardViolations,
            softViolationsByCriterion: $this->softViolationsByCriterion,
            score: $this->score,
            degenerate: $this->degenerate,
            degenerateReason: $this->degenerateReason,
            softCriteriaScores: $this->softCriteriaScores,
            softTeamViolationsByRound: $this->softTeamViolationsByRound,
            strategy: $this->strategy,
            balancedOpponentsViolations: $this->balancedOpponentsViolations,
            provenOptimal: $this->provenOptimal,
            strategyWarning: $strategyWarning,
        );
    }

    /**
     * @return array{key: string, label: string, score: float, weight: float, raw: float, epsilonUnit: float}|null
     */
    public function criterion(string $key): ?array
    {
        foreach ($this->softCriteriaScores as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        return null;
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
            $data['soft_team_violations_by_round'] ?? [],
            $data['strategy'] ?? null,
            $data['balanced_opponents_violations'] ?? [],
            $data['proven_optimal'] ?? null,
            $data['strategy_warning'] ?? null,
        );
    }
}
