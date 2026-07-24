<?php

namespace App\Services\ScheduleGeneration;

/**
 * Recommends a default GenerationStrategy from the league's shape (and,
 * for the seam-variant choice below, the criteria order) - see plan.md
 * "Size-Aware Schedule Generation" §5's "Pre-selection logic". Checked in
 * order:
 *
 * 1. Eligibility. If RoundRobinConstructor::isEligible() is false (a team
 *    with no home venue, three or more active teams sharing one venue, or
 *    more than one shared-venue pair), no seed-based strategy can produce
 *    its intended result, so Greedy is the only strategy that can actually
 *    run - recommended with a reason naming the venue-ownership blocker.
 * 2. Size, once eligibility is satisfied. N <= 6 active teams: Exact - the
 *    exhaustive search comfortably fits its time budget at that size
 *    (plan.md §1f) and finds the true best combination of consecutive_venue/
 *    full_cycle_spacing/home_away_break rather than just a good one. This
 *    takes priority over the regime rule below - a small single-cycle
 *    league (e.g. 4 teams over 3 rounds) still gets Exact recommended, not
 *    SeedOnly, since Exact never does worse than construction alone (it is
 *    seeded with exactly that) and can only improve on it.
 * 3. Regime, for every N > 6 league. Let R = round count:
 *      - R <= N-1 (single-cycle regime): SeedOnly - no rematches, no
 *        pass-boundary seam, breaks already at the theoretical minimum, so
 *        there is nothing for annealing to fix (plan.md §1c).
 *      - R > N-1 (multi-cycle regime): one of the seam-variant strategies
 *        (plan.md §5 Phase 5), chosen by whether consecutive_venue outranks
 *        full_cycle_spacing in $config's own criteria order -
 *        SeedPalindromeAndAnneal if it does (venue variety prioritised, the
 *        shipped default), SeedMirroredAndAnneal otherwise (rematch spacing
 *        prioritised, or consecutive_venue disabled entirely).
 *
 * This is a DEFAULT only (plan.md decision 2.7) - the caller is expected to
 * still offer every GenerationStrategy case regardless of what this
 * recommends, and to surface a poor-fit warning rather than lock out any
 * option (decision 2.6).
 */
final class StrategyRecommender
{
    /**
     * Exact only gets recommended at or below this active team count -
     * plan.md §1f measured the exhaustive search comfortably fitting a 10s
     * budget through 6 teams (6x10's 22,680 orderings took 8.2s in the
     * unoptimized prototype, ~0.55s in the shipped one - see plan.md §11),
     * with 7+ teams already timing out unproven in that same table.
     */
    private const EXACT_MAX_TEAM_COUNT = 6;

    public function __construct(
        private readonly Rng $rng,
    ) {}

    /**
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     */
    public function recommend(array $activeTeams, array $activeVenues, int $roundCount, GenerationConfig $config): StrategyRecommendation
    {
        if (! (new RoundRobinConstructor($this->rng))->isEligible($activeTeams, $activeVenues)) {
            return new StrategyRecommendation(
                GenerationStrategy::Greedy,
                'Venue ownership data is blocking the better strategies: every active team needs its own '
                    .'home venue (at most one venue may be shared by exactly two teams) for the round-robin '
                    .'construction to run. Greedy is the only strategy that can build a schedule for this '
                    .'league as currently set up.',
            );
        }

        $teamCount = count($activeTeams);

        if ($teamCount <= self::EXACT_MAX_TEAM_COUNT) {
            return new StrategyRecommendation(
                GenerationStrategy::Exact,
                "This league has {$teamCount} active teams, small enough for an exhaustive search to finish (or "
                    .'get close) within its time budget - Exact finds the true best combination of venue variety, '
                    .'rematch spacing, and home/away breaks, rather than a good one found by search.',
            );
        }

        $singleCycleLength = max(0, $teamCount - 1);

        if ($roundCount <= $singleCycleLength) {
            return new StrategyRecommendation(
                GenerationStrategy::SeedOnly,
                "This season ({$roundCount} round(s)) fits inside a single round-robin cycle ({$teamCount} "
                    .'teams play every other team once in '.$singleCycleLength.' round(s)): there are no '
                    .'rematches, no pass-boundary seam, and breaks are already at the practical minimum, so '
                    .'there is nothing further search would improve.',
            );
        }

        $seamStrategy = $this->seamVariantFor($config);
        $seamReason = $seamStrategy === GenerationStrategy::SeedPalindromeAndAnneal
            ? 'consecutive_venue outranks full_cycle_spacing in the current criteria order, so the palindrome '
                .'seam (which accepts one reversed-role rematch at each pass boundary in exchange for spreading '
                .'same-venue streaks evenly) is recommended'
            : 'full_cycle_spacing outranks consecutive_venue (or consecutive_venue is not enabled at all) in the '
                .'current criteria order, so the mirrored seam (today\'s default pass-boundary behaviour) is '
                .'recommended';

        return new StrategyRecommendation(
            $seamStrategy,
            "This season ({$roundCount} rounds) spans more than one round-robin cycle ({$teamCount} teams, "
                .$singleCycleLength.' round(s) per cycle), so there is a pass-boundary seam and rematches for '
                ."search to smooth out; {$seamReason}.",
        );
    }

    /**
     * Which seam-variant strategy matches $config's own criteria order
     * (plan.md §5's "seam variant chosen by whether consecutive_venue
     * currently outranks full_cycle_spacing") - palindrome only when
     * consecutive_venue is enabled AND ranked strictly ahead of
     * full_cycle_spacing (or full_cycle_spacing isn't enabled at all);
     * mirrored (today's behaviour) in every other case, including a tie
     * (both in the same tie-group) or consecutive_venue being disabled
     * entirely - a safe default rather than a guess when the ordering
     * doesn't clearly favour venue variety.
     */
    private function seamVariantFor(GenerationConfig $config): GenerationStrategy
    {
        $consecutiveVenueRank = null;
        $fullCycleSpacingRank = null;

        foreach ($config->tiers() as $rank => $tierKeys) {
            if (in_array('consecutive_venue', $tierKeys, true)) {
                $consecutiveVenueRank = $rank;
            }

            if (in_array('full_cycle_spacing', $tierKeys, true)) {
                $fullCycleSpacingRank = $rank;
            }
        }

        $consecutiveVenueOutranks = $consecutiveVenueRank !== null
            && ($fullCycleSpacingRank === null || $consecutiveVenueRank < $fullCycleSpacingRank);

        return $consecutiveVenueOutranks
            ? GenerationStrategy::SeedPalindromeAndAnneal
            : GenerationStrategy::SeedMirroredAndAnneal;
    }
}
