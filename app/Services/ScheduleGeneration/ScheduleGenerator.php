<?php

namespace App\Services\ScheduleGeneration;

/**
 * Construct-then-anneal: builds one hard-valid seed candidate
 * (InitialSolutionBuilder), then locally improves it via simulated
 * annealing (SimulatedAnnealingOptimizer) within the attempt/time budget.
 * Never true exhaustive brute force - intractable in general and
 * unnecessary at league scale.
 */
final class ScheduleGenerator
{
    /**
     * Bounded feasibility retries for the construction phase only - see the
     * comment at its call site. RoundRobinConstructor never needs more than
     * one (it's deterministic and always hard-valid); this budget exists for
     * the greedy fallback path on tightly venue-constrained inputs.
     */
    private const MAX_SEED_ATTEMPTS = 20;

    public function __construct(
        private readonly Rng $rng,
        private readonly ScheduleScorer $scorer,
    ) {
    }

    /**
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    public function generate(array $rounds, array $activeTeams, array $activeVenues, GenerationConfig $config): GenerationResult
    {
        $startedAt = microtime(true);

        if (empty($rounds)) {
            return $this->degenerateResult(
                new ScheduleCandidate([]),
                "No rounds are available to assign for this schedule.",
                0,
                $this->elapsedMs($startedAt),
            );
        }

        if (count($activeTeams) < 2) {
            return $this->degenerateResult(
                $this->allByeCandidate($rounds, $activeTeams),
                'Fewer than 2 active teams are available, so no matches can be scheduled.',
                0,
                $this->elapsedMs($startedAt),
            );
        }

        if (empty($activeVenues)) {
            return $this->degenerateResult(
                $this->allByeCandidate($rounds, $activeTeams),
                'No active venues are available, so no matches can be scheduled.',
                0,
                $this->elapsedMs($startedAt),
            );
        }

        $attempts = 0;
        $best = null;
        $bestReport = null;

        // Construction phase: a strong deterministic seed when every active
        // team owns a distinct home venue (RoundRobinConstructor) - always
        // hard-valid by construction, so this never needs a second try.
        // Otherwise a single greedy pass, which is hard-valid for almost any
        // input but can occasionally fail on a tightly venue-constrained one
        // (e.g. very few active venues shared across teams) - bounded retries
        // here are a feasibility search only (a fresh RNG-driven shuffle each
        // try), not a quality search, which the polish phase below owns.
        $seed = null;
        $seedReport = null;

        for ($seedAttempt = 0; $seedAttempt < self::MAX_SEED_ATTEMPTS; $seedAttempt++) {
            $candidateSeed = (new InitialSolutionBuilder($this->rng))->build($rounds, $activeTeams, $activeVenues);
            $candidateSeedReport = $this->scorer->score($candidateSeed, $activeTeams, $activeVenues, $config);

            if ($candidateSeedReport->hardConstraintsSatisfied) {
                $seed = $candidateSeed;
                $seedReport = $candidateSeedReport;

                break;
            }
        }

        if ($seed !== null) {
            if ($seedReport->score <= 0.0) {
                return new GenerationResult($seed, $seedReport, $attempts, $this->elapsedMs($startedAt));
            }

            // Polish phase: simulated annealing with reheat-on-new-best,
            // replacing plain randomized restart (see
            // SimulatedAnnealingOptimizer).
            $outcome = (new SimulatedAnnealingOptimizer($this->rng, $this->scorer))->optimize(
                $seed,
                $seedReport,
                $rounds,
                $activeTeams,
                $activeVenues,
                $config,
                $startedAt,
            );

            $best = $outcome['candidate'];
            $bestReport = $outcome['report'];
            $attempts = $outcome['iterations'];
        }

        if ($best === null) {
            // Every attempt within budget failed to satisfy the hard
            // constraints (in practice RoundBuilder's own construction can't
            // violate any of the remaining ones, so this shouldn't happen -
            // but it keeps a concrete, honestly-flagged-degenerate schedule
            // available if it ever does). Run one more attempt purely so
            // there's something to show the admin.
            $fallback = $this->attempt($rounds, $activeTeams);
            $report = $this->scorer->score($fallback, $activeTeams, $activeVenues, $config);
            $reason = "Could not find a schedule that satisfies all required constraints within {$attempts} attempts.";

            return new GenerationResult(
                $fallback,
                new GenerationReport(
                    hardConstraintsSatisfied: false,
                    hardViolations: $report->hardViolations,
                    softViolationsByCriterion: $report->softViolationsByCriterion,
                    score: $report->score,
                    degenerate: true,
                    degenerateReason: $reason,
                    softCriteriaScores: $report->softCriteriaScores,
                ),
                $attempts,
                $this->elapsedMs($startedAt),
            );
        }

        return new GenerationResult($best, $bestReport, $attempts, $this->elapsedMs($startedAt));
    }

    /**
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     */
    private function attempt(array $rounds, array $activeTeams): ScheduleCandidate
    {
        return (new InitialSolutionBuilder($this->rng))->greedyPass($rounds, $activeTeams);
    }

    /**
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     */
    private function allByeCandidate(array $rounds, array $activeTeams): ScheduleCandidate
    {
        $teamIds = array_map(fn (TeamInput $t) => $t->id, $activeTeams);

        $roundCandidates = array_map(
            fn (RoundInput $round) => new RoundCandidate($round->date, [], $teamIds, $round->roundId),
            $rounds,
        );

        return new ScheduleCandidate($roundCandidates);
    }

    private function degenerateResult(ScheduleCandidate $candidate, string $reason, int $attempts, float $elapsedMs): GenerationResult
    {
        return new GenerationResult(
            $candidate,
            new GenerationReport(
                hardConstraintsSatisfied: true,
                hardViolations: [],
                softViolationsByCriterion: [],
                score: 0.0,
                degenerate: true,
                degenerateReason: $reason,
            ),
            $attempts,
            $elapsedMs,
        );
    }

    private function elapsedMs(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }
}
