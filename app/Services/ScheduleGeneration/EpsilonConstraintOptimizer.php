<?php

namespace App\Services\ScheduleGeneration;

/**
 * Sequential (textbook) epsilon-constraint search over the soft-criteria
 * priority tiers ($config->tiers() - any subset/order an Association
 * enables, see GenerationConfig::DEFAULT_SOFT_CRITERIA), layered on top of -
 * not a replacement for - big-M dominance weighting
 * (GenerationConfig::tierWeight()): instead of one SimulatedAnnealingOptimizer
 * pass whose objective big-M-weights ALL enabled tiers for the entire
 * search, this runs one pass per tier, where pass m's objective still
 * big-M-weights every tier from m onward exactly as before (tier m
 * dominates the search, later tiers ride along as free tie-breaks) - the
 * only thing that changes pass to pass is that already-fixed tiers
 * (0..m-1) drop out of the weighted objective entirely and become a hard-
 * constraint-style gate instead, via $fixedTierThresholds. That gate is
 * what actually buys the tolerance big-M alone can't: a tier's best
 * achieved raw penalty plus a small slack (that criterion's own
 * epsilonUnit()) is fixed as a ceiling once its pass completes, rather than
 * remaining an unbounded-dominance term forever - so a later pass CAN
 * worsen an earlier tier by up to one raw unit if that's the only way it
 * found to improve a lower-priority one, which plain big-M weighting (zero
 * tolerance for any tradeoff, no matter how large the benefit lower down)
 * never allows.
 *
 * A tier may hold 2+ CO-EQUAL ("tied") criteria instead of just one - that
 * tier's pass is delegated to ChebyshevTieBreak instead of a plain
 * SimulatedAnnealingOptimizer call, which resolves the tied members via
 * minimax on normalized regret rather than a big-M-weighted sum among
 * themselves (see that class's own docblock for why). Every member of a
 * completed tied tier gets fixed into $fixedTierThresholds, same as a
 * singleton tier's one key.
 *
 * "Tier" here is always a rank position in $config->tiers(), not a fixed
 * criterion key - since softCriteria is configurable per Association,
 * whichever tier currently occupies rank 1 is what gets optimized (and
 * fixed) first, exactly as GenerationConfig::tierWeight() already resolves
 * rank from the same structure.
 */
final class EpsilonConstraintOptimizer
{
    public function __construct(
        private readonly Rng $rng,
        private readonly ScheduleScorer $scorer,
    ) {
    }

    /**
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     * @return array{candidate: ScheduleCandidate, report: GenerationReport, iterations: int}
     */
    public function optimize(
        ScheduleCandidate $initial,
        GenerationReport $initialReport,
        array $rounds,
        array $activeTeams,
        array $activeVenues,
        GenerationConfig $config,
    ): array {
        $tiers = $config->tiers();
        $tierCount = count($tiers);

        if (empty($rounds) || $tierCount === 0 || $initialReport->score <= 0.0) {
            return ['candidate' => $initial, 'report' => $initialReport, 'iterations' => 0];
        }

        // Weights for splitting the overall attempt/time budget across the
        // tierCount sequential passes: pass for rank 0 (highest priority)
        // gets the largest share, tapering to the smallest share for the
        // last rank. An early tier's suboptimality is irrevocable once
        // fixed, a late tier's isn't, so more search effort belongs where
        // mistakes can't be undone later.
        $weightDenominator = array_sum(range(1, $tierCount));

        // The TOTAL attempts budget scales with problem size rather than
        // being a flat number: target searchEpochs full sweeps of this
        // schedule's own neighborhood (SlotCount::total($rounds)), capped by
        // maxAttempts as a defensive ceiling. By design (see
        // GenerationConfig::DEFAULT_SEARCH_EPOCHS) that ceiling only binds
        // around a 16-team/16-venue/10-round schedule and up - LARGER
        // league sizes are out of scope for now and simply get capped here
        // rather than scaling further - or when a caller deliberately
        // passes a small maxAttempts to force a tight ceiling. timeBudgetMs
        // is NOT scaled the same
        // way - it stays maxAttempts's flat wall-clock counterpart, per
        // GenerationConfig's own docblock.
        $totalAttempts = min(
            $config->maxAttempts,
            max(1, $config->searchEpochs) * max(1, SlotCount::total($rounds)),
        );

        $current = $initial;
        $fixedThresholds = [];
        $totalIterations = 0;

        for ($m = 0; $m < $tierCount; $m++) {
            $tierKeys = $tiers[$m];
            $earlierTiers = array_slice($tiers, 0, $m);
            $excluded = $earlierTiers === [] ? [] : array_merge(...$earlierTiers);
            $shareWeight = $tierCount - $m;

            $passConfig = new GenerationConfig(
                maxAttempts: intdiv($totalAttempts * $shareWeight, $weightDenominator),
                timeBudgetMs: intdiv($config->timeBudgetMs * $shareWeight, $weightDenominator),
                softCriteria: $config->softCriteria,
                excludedFromObjective: $excluded,
                // Must be forwarded: this pass config is what SimulatedAnnealingOptimizer
                // actually scores against, so dropping it would silently re-enable the
                // balanced-opponents hard constraint for the whole search even when the
                // caller (the greedy strategy) deliberately turned it off.
                enforceBalancedOpponents: $config->enforceBalancedOpponents,
            );

            if (count($tierKeys) === 1) {
                // Re-score under THIS pass's objective - GenerationReport.score
                // depends on $config (which tiers are weighted), so a prior
                // pass's report can't be reused as this pass's accept/reject
                // baseline. raw/epsilonUnit are config-independent, so reading
                // them off any report for the winning candidate is safe.
                $passSeedReport = $this->scorer->score($current, $activeTeams, $activeVenues, $passConfig);

                $outcome = (new SimulatedAnnealingOptimizer($this->rng, $this->scorer))->optimize(
                    $current,
                    $passSeedReport,
                    $rounds,
                    $activeTeams,
                    $activeVenues,
                    $passConfig,
                    microtime(true),
                    $fixedThresholds,
                );
            } else {
                $outcome = (new ChebyshevTieBreak($this->rng, $this->scorer))->optimize(
                    $tierKeys,
                    $current,
                    $rounds,
                    $activeTeams,
                    $activeVenues,
                    $passConfig,
                    $fixedThresholds,
                );
            }

            $current = $outcome['candidate'];
            $totalIterations += $outcome['iterations'];

            foreach ($tierKeys as $tierKey) {
                $entry = $this->findCriterionEntry($outcome['report'], $tierKey);
                $fixedThresholds[$tierKey] = $entry['raw'] + $entry['epsilonUnit'];
            }
        }

        // The final report reflects the caller's original, unrestricted
        // config - not the last pass's near-fully-excluded one - so it's
        // comparable to the seed's pre-optimization score and across
        // separate generate() calls.
        $finalReport = $this->scorer->score($current, $activeTeams, $activeVenues, $config);

        return ['candidate' => $current, 'report' => $finalReport, 'iterations' => $totalIterations];
    }

    /**
     * @return array{key: string, label: string, score: float, weight: float, raw: float, epsilonUnit: float}
     */
    private function findCriterionEntry(GenerationReport $report, string $key): array
    {
        return $report->criterion($key) ?? throw new \LogicException("No soft criterion scored under key '{$key}'.");
    }
}
