<?php

namespace App\Services\ScheduleGeneration;

use App\Services\ScheduleGeneration\HardConstraints\BalancedOpponentMeetingsConstraint;

/**
 * Construct-then-anneal: builds one hard-valid seed candidate
 * (InitialSolutionBuilder), then locally improves it via sequential
 * epsilon-constraint search (EpsilonConstraintOptimizer, one simulated
 * annealing pass per soft-criteria priority tier) within the attempt/time
 * budget. Never true exhaustive brute force - intractable in general and
 * unnecessary at league scale.
 *
 * Which construction phase runs, and whether the polish phase runs at all,
 * is governed by the $strategy parameter (see GenerationStrategy) - plan.md
 * "Size-Aware Schedule Generation" §5. GenerationStrategy::SeedAndAnneal is
 * the default and reproduces this class's pre-strategy behaviour exactly
 * (a non-regression requirement, not just a convenience default).
 */
final class ScheduleGenerator
{
    /**
     * Bounded feasibility retries for the construction phase only - see the
     * comment at its call site. RoundRobinConstructor never needs more than
     * one (its team-to-slot assignment is randomized for fairness - see its
     * own docblock - but every arrangement it can produce is always hard-
     * valid by construction); this budget exists for the greedy fallback
     * path on tightly venue-constrained inputs.
     */
    private const MAX_SEED_ATTEMPTS = 20;

    public function __construct(
        private readonly Rng $rng,
        private readonly ScheduleScorer $scorer,
    ) {}

    /**
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     */
    public function generate(
        array $rounds,
        array $activeTeams,
        array $activeVenues,
        GenerationConfig $config,
        GenerationStrategy $strategy = GenerationStrategy::SeedAndAnneal,
    ): GenerationResult {
        $startedAt = microtime(true);

        if (empty($rounds)) {
            return $this->degenerateResult(
                new ScheduleCandidate([]),
                'No rounds are available to assign for this schedule.',
                0,
                $this->elapsedMs($startedAt),
                $strategy,
            );
        }

        if (count($activeTeams) < 2) {
            return $this->degenerateResult(
                $this->allByeCandidate($rounds, $activeTeams),
                'Fewer than 2 active teams are available, so no matches can be scheduled.',
                0,
                $this->elapsedMs($startedAt),
                $strategy,
            );
        }

        if (empty($activeVenues)) {
            return $this->degenerateResult(
                $this->allByeCandidate($rounds, $activeTeams),
                'No active venues are available, so no matches can be scheduled.',
                0,
                $this->elapsedMs($startedAt),
                $strategy,
            );
        }

        // Exact bypasses the construct-then-anneal pipeline entirely (it IS
        // its own search, not a seed for one) so it is dispatched separately
        // before anything below - see generateExact()'s own docblock for the
        // eligibility check and soft-failure fallback (plan.md §6, decision
        // 2.6).
        if ($strategy === GenerationStrategy::Exact) {
            return $this->generateExact($rounds, $activeTeams, $activeVenues, $config, $startedAt);
        }

        // The strategy governs both which construction phase runs below AND
        // whether BalancedOpponentMeetingsConstraint is enforced as a hard
        // constraint - see GenerationStrategy::enforceBalancedOpponents().
        // This intentionally overrides whatever $config itself carried for
        // that flag: it is a property of which pipeline is actually running,
        // not an independent per-Association preference (nothing in
        // GenerationConfig::forAssociation()/fromConfig() varies it either).
        $config = $this->configForStrategy($config, $strategy);

        $attempts = 0;
        $best = null;
        $bestReport = null;

        // Construction phase - which seed gets built depends on $strategy
        // (see buildSeed()). For SeedOnly/SeedAndAnneal this is a strong
        // break-minimal seed when every active team owns a distinct home
        // venue (RoundRobinConstructor, team-to-slot assignment randomized
        // for fairness but always hard-valid by construction), falling back
        // to a single greedy pass otherwise; Greedy always uses the greedy
        // pass directly, skipping RoundRobinConstructor even when it would
        // have been eligible. Either path is hard-valid for almost any input
        // but the greedy pass can occasionally fail on a tightly
        // venue-constrained one (e.g. very few active venues shared across
        // teams) - bounded retries here are a feasibility search only (a
        // fresh RNG-driven shuffle each try), not a quality search, which
        // the polish phase below owns.
        $seed = null;
        $seedReport = null;

        for ($seedAttempt = 0; $seedAttempt < self::MAX_SEED_ATTEMPTS; $seedAttempt++) {
            $candidateSeed = $this->buildSeed($rounds, $activeTeams, $activeVenues, $strategy);
            $candidateSeedReport = $this->scorer->score($candidateSeed, $activeTeams, $activeVenues, $config);

            if ($candidateSeedReport->hardConstraintsSatisfied) {
                $seed = $candidateSeed;
                $seedReport = $candidateSeedReport;

                break;
            }
        }

        if ($seed !== null) {
            // SeedOnly stops here by definition - no polish phase at all,
            // regardless of score. This is the one branch point that isn't
            // present for the default strategy, so SeedAndAnneal's own
            // control flow below is completely untouched by this addition.
            if ($strategy === GenerationStrategy::SeedOnly || $seedReport->score <= 0.0) {
                return new GenerationResult(
                    $seed,
                    $seedReport->withStrategyMetadata($strategy, $this->balancedOpponentsViolations($seed, $activeTeams, $activeVenues, $strategy)),
                    $attempts,
                    $this->elapsedMs($startedAt),
                );
            }

            // Polish phase: sequential epsilon-constraint search, one
            // simulated-annealing pass per priority tier (see
            // EpsilonConstraintOptimizer). Its own budget is measured from
            // its own invocation rather than $startedAt, since it manages
            // per-pass clocks internally.
            $outcome = (new EpsilonConstraintOptimizer($this->rng, $this->scorer))->optimize(
                $seed,
                $seedReport,
                $rounds,
                $activeTeams,
                $activeVenues,
                $config,
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
                    strategy: $strategy->value,
                ),
                $attempts,
                $this->elapsedMs($startedAt),
            );
        }

        return new GenerationResult(
            $best,
            $bestReport->withStrategyMetadata($strategy, $this->balancedOpponentsViolations($best, $activeTeams, $activeVenues, $strategy)),
            $attempts,
            $this->elapsedMs($startedAt),
        );
    }

    /**
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     */
    private function buildSeed(array $rounds, array $activeTeams, array $activeVenues, GenerationStrategy $strategy): ScheduleCandidate
    {
        $builder = new InitialSolutionBuilder($this->rng);

        // Greedy deliberately skips RoundRobinConstructor even when the
        // input would have been eligible for it (plan.md §5) - the whole
        // point of choosing Greedy is to run the greedy pass, not to
        // silently get the seed-based construction back. Both other
        // strategies use InitialSolutionBuilder::build()'s own eligibility
        // check (construction when eligible, greedy fallback otherwise) -
        // exactly what today's single hardcoded pipeline already did.
        return $strategy === GenerationStrategy::Greedy
            ? $builder->greedyPass($rounds, $activeTeams)
            : $builder->build($rounds, $activeTeams, $activeVenues, $strategy->usesPalindromeSeam());
    }

    /**
     * GenerationStrategy::Exact's entry point (plan.md §6). Runs ExactSolver
     * directly rather than going through buildSeed()/the polish loop above -
     * the exact solver IS the whole search, seeded internally with its own
     * RoundRobinConstructor incumbent (see ExactSolver::solve()'s own safety
     * guarantee).
     *
     * Decision 2.6 (soft failure, never a locked door): an admin is always
     * allowed to pick Exact even for a league whose venue ownership data
     * makes RoundRobinConstructor - and therefore ExactSolver, which reuses
     * it - ineligible. solve() THROWS in that situation (mirroring
     * RoundRobinConstructor::construct()'s own precondition), so eligibility
     * is checked here FIRST and, on failure, this degrades to the Greedy
     * pipeline with a clear warning attached to the report rather than
     * letting that exception reach the controller. The eligibility check
     * covers every documented throw condition, but solve() is wrapped in a
     * try/catch as well purely as a defensive backstop - Exact must never be
     * the one strategy capable of turning a review-screen visit into a 500.
     *
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     */
    private function generateExact(
        array $rounds,
        array $activeTeams,
        array $activeVenues,
        GenerationConfig $config,
        float $startedAt,
    ): GenerationResult {
        $solver = new ExactSolver($this->rng);

        if (! $solver->isEligible($activeTeams, $activeVenues)) {
            return $this->degradeExactToGreedy($rounds, $activeTeams, $activeVenues, $config, $startedAt);
        }

        $exactConfig = $this->configForStrategy($config, GenerationStrategy::Exact);

        try {
            $result = $solver->solve($rounds, $activeTeams, $activeVenues, $exactConfig, $config->exactSolverTimeBudgetMs);
        } catch (\RuntimeException) {
            return $this->degradeExactToGreedy($rounds, $activeTeams, $activeVenues, $config, $startedAt);
        }

        return new GenerationResult(
            $result->candidate,
            $result->report->withStrategyMetadata(GenerationStrategy::Exact, provenOptimal: $result->provenOptimal),
            $result->orderingsExplored,
            $this->elapsedMs($startedAt),
        );
    }

    /**
     * The soft-failure path generateExact() takes when ExactSolver can't run
     * at all - reruns the whole pipeline as Greedy (a full recursive
     * generate() call, not a partial one, so it gets every one of Greedy's
     * own guarantees/behaviour unchanged) and stamps the resulting report
     * with a warning naming what was requested and why it couldn't run, so
     * the review screen can surface it (plan.md §7 - "any warning from a
     * poor-fit strategy choice"). $startedAt is the ORIGINAL call's start
     * time, not a fresh one, so elapsedMs reflects the whole detour.
     *
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     */
    private function degradeExactToGreedy(
        array $rounds,
        array $activeTeams,
        array $activeVenues,
        GenerationConfig $config,
        float $startedAt,
    ): GenerationResult {
        $fallback = $this->generate($rounds, $activeTeams, $activeVenues, $config, GenerationStrategy::Greedy);

        return new GenerationResult(
            $fallback->candidate,
            $fallback->report->withStrategyWarning(
                'Exact was selected, but this league\'s venue ownership data isn\'t eligible for the round-robin '
                    .'construction Exact requires (every active team needs its own home venue, or exactly one '
                    .'venue may be shared by exactly two teams). Generated with Greedy instead.'
            ),
            $fallback->attemptsUsed,
            $this->elapsedMs($startedAt),
        );
    }

    /**
     * A strategy's enforceBalancedOpponents() characteristic overrides
     * whatever $config carried for that flag - see the call site's comment
     * in generate(). Every other field is passed through unchanged.
     */
    private function configForStrategy(GenerationConfig $config, GenerationStrategy $strategy): GenerationConfig
    {
        return new GenerationConfig(
            maxAttempts: $config->maxAttempts,
            timeBudgetMs: $config->timeBudgetMs,
            searchEpochs: $config->searchEpochs,
            softCriteria: $config->softCriteria,
            excludedFromObjective: $config->excludedFromObjective,
            enforceBalancedOpponents: $strategy->enforceBalancedOpponents(),
            exactSolverTimeBudgetMs: $config->exactSolverTimeBudgetMs,
        );
    }

    /**
     * BalancedOpponentMeetingsConstraint's violation messages against
     * $candidate, checked directly rather than via a full ScheduleScorer
     * re-score - only meaningful (and only ever non-empty) when $strategy
     * ran with the constraint OFF as a hard gate (currently just Greedy -
     * see GenerationStrategy::enforceBalancedOpponents()); when it was
     * enforced, a violating candidate could never have reached this point,
     * so this always returns [] without bothering to check. Feeds
     * GenerationReport::$balancedOpponentsViolations, the soft warning the
     * review screen surfaces for decision 2.6 ("soft failure, not a locked
     * door").
     *
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     * @return string[]
     */
    private function balancedOpponentsViolations(ScheduleCandidate $candidate, array $activeTeams, array $activeVenues, GenerationStrategy $strategy): array
    {
        if ($strategy->enforceBalancedOpponents()) {
            return [];
        }

        $context = ScoringContext::build($activeTeams, $activeVenues);
        $constraint = new BalancedOpponentMeetingsConstraint($context);

        foreach ($candidate->rounds as $roundIndex => $round) {
            $constraint->startRound($roundIndex);

            foreach ($round->matches as $match) {
                $constraint->observeMatch($roundIndex, $match);
            }
        }

        return $constraint->violations();
    }

    /**
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     */
    private function attempt(array $rounds, array $activeTeams): ScheduleCandidate
    {
        return (new InitialSolutionBuilder($this->rng))->greedyPass($rounds, $activeTeams);
    }

    /**
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
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

    private function degenerateResult(ScheduleCandidate $candidate, string $reason, int $attempts, float $elapsedMs, GenerationStrategy $strategy): GenerationResult
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
                strategy: $strategy->value,
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
