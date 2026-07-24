<?php

namespace App\Services\ScheduleGeneration;

/**
 * Resolves a tied (co-equal priority) tier's 2+ soft-criterion members via
 * Chebyshev/minimax scalarization, for EpsilonConstraintOptimizer: rather
 * than summing the tier's members into one additive objective - which lets
 * an arbitrary-ratio trade between them, e.g. sacrificing one member
 * entirely for a small gain in the other, with no protection against a
 * lopsided outcome - this first probes each member's own best-achievable
 * raw penalty in isolation (its "ideal point"), then runs ONE joint SA pass
 * whose objective is the WORST normalized regret-from-ideal across the
 * tier's members. A move that helps the currently-better-off member at the
 * currently-worse-off member's expense is rejected until the worse-off one
 * stops being the bottleneck; nothing stops the reverse (trading a
 * still-better-off member's slack to help the worse-off one further),
 * which is exactly what minimax is for.
 *
 * GOAL PROGRAMMING is a strong candidate to explore later as a swappable
 * alternative to this: instead of probing for each member's best-possible
 * value and minimizing the worst regret from it, goal programming sets a
 * target/aspiration level per member up front and minimizes weighted
 * deviation BELOW that target - a different (and for some tie-groups
 * possibly more useful) notion of "balance," answering "did we hit
 * good-enough on both?" rather than "how close is the worse one to its own
 * best possible?" Not implemented here - there is only this one
 * implementation today, so a formal pluggable-strategy interface would be
 * premature; this docblock is the marker for that future direction.
 */
final class ChebyshevTieBreak
{
    /**
     * Half the tier's pass budget is split (evenly) across the per-member
     * ideal-point probes below; the rest goes to the joint pass. A simple,
     * tunable ratio - not scientifically calibrated - matching how
     * SimulatedAnnealingOptimizer's own PROBE_MOVES/ACCEPTANCE_QUANTILE are
     * heuristic constants rather than derived values.
     */
    private const PROBE_SHARE = 0.5;

    public function __construct(
        private readonly Rng $rng,
        private readonly ScheduleScorer $scorer,
    ) {
    }

    /**
     * @param string[] $tierKeys the tied tier's member criterion keys (2+)
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     * @param array<string, float> $fixedTierThresholds see SimulatedAnnealingOptimizer::optimize()
     * @return array{candidate: ScheduleCandidate, report: GenerationReport, iterations: int}
     */
    public function optimize(
        array $tierKeys,
        ScheduleCandidate $seed,
        array $rounds,
        array $activeTeams,
        array $activeVenues,
        GenerationConfig $passConfig,
        array $fixedTierThresholds,
    ): array {
        $memberCount = count($tierKeys);
        $probeShareEach = max(1, intdiv((int) ($passConfig->maxAttempts * self::PROBE_SHARE), $memberCount));
        $probeTimeShareEach = max(1, intdiv((int) ($passConfig->timeBudgetMs * self::PROBE_SHARE), $memberCount));

        $idealRaw = [];
        $totalIterations = 0;

        // Each member is probed independently FROM THE SAME SEED (never
        // chained probe-to-probe), so a fair "what's achievable for THIS
        // member alone, from here" ideal point isn't biased by whatever an
        // earlier member's own single-minded probe happened to find.
        foreach ($tierKeys as $key) {
            $siblingKeys = array_values(array_diff($tierKeys, [$key]));

            $probeConfig = new GenerationConfig(
                maxAttempts: $probeShareEach,
                timeBudgetMs: $probeTimeShareEach,
                softCriteria: $passConfig->softCriteria,
                excludedFromObjective: array_merge($passConfig->excludedFromObjective, $siblingKeys),
                enforceBalancedOpponents: $passConfig->enforceBalancedOpponents,
            );

            $probeSeedReport = $this->scorer->score($seed, $activeTeams, $activeVenues, $probeConfig);

            $probeOutcome = (new SimulatedAnnealingOptimizer($this->rng, $this->scorer))->optimize(
                $seed, $probeSeedReport, $rounds, $activeTeams, $activeVenues, $probeConfig, microtime(true), $fixedTierThresholds,
            );

            $totalIterations += $probeOutcome['iterations'];
            $idealRaw[$key] = $probeOutcome['report']->criterion($key)['raw']
                ?? throw new \LogicException("No soft criterion scored under key '{$key}'.");
        }

        $tierWeightValue = $passConfig->tierWeight($tierKeys[0]); // identical for every member, by definition of "tied"

        $objective = function (GenerationReport $report) use ($tierKeys, $idealRaw, $tierWeightValue): float {
            $tiedContribution = 0.0;
            $worstRegret = 0.0;

            foreach ($tierKeys as $key) {
                $entry = $report->criterion($key) ?? throw new \LogicException("No soft criterion scored under key '{$key}'.");
                $tiedContribution += $entry['weight'] * $entry['raw'];
                $ideal = max($idealRaw[$key], $entry['epsilonUnit']);
                $worstRegret = max($worstRegret, $entry['raw'] / $ideal);
            }

            return $report->score - $tiedContribution + $tierWeightValue * $worstRegret;
        };

        $jointConfig = new GenerationConfig(
            maxAttempts: max(1, $passConfig->maxAttempts - $probeShareEach * $memberCount),
            timeBudgetMs: max(1, $passConfig->timeBudgetMs - $probeTimeShareEach * $memberCount),
            softCriteria: $passConfig->softCriteria,
            excludedFromObjective: $passConfig->excludedFromObjective,
            enforceBalancedOpponents: $passConfig->enforceBalancedOpponents,
        );

        $jointSeedReport = $this->scorer->score($seed, $activeTeams, $activeVenues, $jointConfig);

        $jointOutcome = (new SimulatedAnnealingOptimizer($this->rng, $this->scorer))->optimize(
            $seed, $jointSeedReport, $rounds, $activeTeams, $activeVenues, $jointConfig, microtime(true), $fixedTierThresholds, $objective,
        );

        $totalIterations += $jointOutcome['iterations'];

        return ['candidate' => $jointOutcome['candidate'], 'report' => $jointOutcome['report'], 'iterations' => $totalIterations];
    }
}
