<?php

namespace App\Services\ScheduleGeneration;

/**
 * Exhaustive, provably-optimal (within budget) schedule search for small
 * exclusive-home-venue leagues - see plan.md, "Size-Aware Schedule
 * Generation - Strategy Selection, Exact Solver, and a Corrected
 * Objective", section 6. Do not confuse this with a naive DFS over every
 * possible matching: that was measured to time out well inside the N<=6
 * target (plan.md 1f). This class instead exploits a decomposition that
 * IS tractable:
 *
 *  - WHICH pairings happen in which round determines round-robin balance
 *    and full_cycle_spacing, and is searched by enumerating ORDERINGS of
 *    a single canonical circle-method cycle (built by
 *    RoundRobinConstructor::buildSingleCycle(), reused rather than
 *    reimplemented - see that method's docblock for the shared contract).
 *    Every cycle round is used as close to equally often as the round
 *    count allows (the first `rounds mod cycleLength` cycle rounds, in
 *    cycle order, get one extra use), which makes balanced opponent
 *    meetings automatic rather than a constraint to search for. Round 0
 *    is fixed to cycle round 0 in every ordering: swapping which cycle
 *    round gets called "0" is a pure relabelling of an otherwise abstract
 *    pattern (no team identity exists yet - see below), so exploring
 *    those relabellings would be wasted work. This exact scheme was
 *    reverse-derived from the orderings counts measured in plan.md 1f
 *    (e.g. 4x6 -> 30, 4x10 -> 1,680, 5x10/6x10 -> 22,680) and reproduces
 *    them precisely; if a future change to the enumeration rule ever
 *    stops matching that table, treat it as a regression.
 *
 *  - WHO hosts each match, given the pairings are fixed, is solved
 *    EXACTLY by dynamic programming rather than searched: consecutive_venue
 *    and home_away_break both depend only on a team's role in ADJACENT
 *    rounds, so the DP state is simply "the previous round's per-slot
 *    home/away orientation" (one of 2^k choices, k = floor(activeTeamCount
 *    / 2) real matches per round, independent per match - NOT a single
 *    global flip bit). Transition costs between every pair of cycle
 *    rounds and every pair of orientations are precomputed once up front
 *    (buildTransitionTable()) so each ordering's DP is a handful of table
 *    lookups, not a re-derivation from scratch - this is what keeps
 *    22,680 orderings inside budget.
 *
 * Everything above operates on ABSTRACT SLOTS, not teams - no soft
 * criterion this solver optimises distinguishes one team from another, so
 * the optimum is a property of the slot pattern alone. Team identity is
 * introduced exactly once, via RoundRobinConstructor::assignTeamsToSlots()
 * (also reused rather than reimplemented, so the shared-venue-pair
 * safe-slot logic documented on that class is honoured here too), and the
 * same random slot assignment is used both to
 * score every candidate during the search AND as the final output -
 * splitting that into a "search phase" mapping and a separate "final
 * randomisation" step would just be two independent random draws done in
 * a different order, which changes nothing about fairness (see
 * RoundRobinConstructor's own docblock for why randomising the mapping
 * matters at all).
 *
 * CRITICAL SUBTLETY - why the DP is a LOWER BOUND, not the real score.
 * ConsecutiveVenueCriterion adds a repeat-offender surcharge based on a
 * team's TOTAL count across the whole schedule, and HomeAwayBreakCriterion
 * applies a 3x multiplier once a streak reaches 3 - neither is expressible
 * in a DP whose state is only "the previous round's orientation". So the
 * DP instead optimises a LINEAR PROXY: every genuine incident (see
 * buildTransitionTable() for the precise venue-repeat test, which does
 * account for away-vs-away meaning a DIFFERENT venue unless it is a
 * same-opponent repeat - not merely "same role twice") costs exactly 1,
 * with no surcharge and no multiplier. Because both the surcharge and the
 * multiplier only ever ADD to the real penalty relative to a flat per-
 * incident count, the proxy is a provable lower bound on the real raw
 * penalty for ANY orientation of a given ordering - which is exactly what
 * is needed for branch-and-bound pruning (see evaluateOrdering()), even
 * though the DP's own proxy-optimal orientation is not guaranteed to be
 * the REAL-score-optimal orientation for that ordering (two orientations
 * tied on proxy count can differ on how concentrated the surcharge ends
 * up). This is why every ordering that survives pruning is still
 * materialised into a full ScheduleCandidate and scored for real via
 * ScheduleScorer, with only the best REAL score kept - the DP proxy is
 * used to prune the search, never to decide the winner.
 *
 * EXACTNESS IS PER-CRITERION. consecutive_venue and home_away_break are
 * exact via the DP (modulo the proxy-vs-real subtlety above, resolved by
 * always re-scoring for real). full_cycle_spacing is exact directly - it
 * depends only on the pairing order, computed once per ordering with no
 * DP needed. Every OTHER soft criterion (home_venue_balance,
 * equal_matches_played, balanced_opponents, ...) is neither optimised nor
 * bounded by this solver; if the caller's config enables one, the search
 * still runs (and still never returns worse than the seed - see below),
 * it simply is not what the search is choosing between.
 *
 * KNOWN LIMITATION - exact only within this cycle family. The enumeration
 * explores orderings of ONE canonical circle-method cycle. It does not
 * consider structurally different pairing cycles. This was cross-checked
 * against a full brute force at 4 teams only (plan.md 1a/1e) and matched
 * exactly; do not claim global optimality beyond N=4 in code or copy.
 *
 * SAFETY. The incumbent is seeded with RoundRobinConstructor's own
 * construct() output (scored for real) before the search runs, so a
 * result is never worse than what construction alone would have produced
 * - pruning is effective from the very first ordering considered, and a
 * budget expiry simply stops the search with whatever has been found so
 * far, never below the seed. provenOptimal is true only when the entire
 * ordering enumeration completed inside the wall-clock budget.
 */
final class ExactSolver
{
    public function __construct(
        private readonly Rng $rng,
    ) {}

    /**
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     */
    public function isEligible(array $activeTeams, array $activeVenues): bool
    {
        return (new RoundRobinConstructor($this->rng))->isEligible($activeTeams, $activeVenues);
    }

    /**
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     *
     * @throws \RuntimeException if the input isn't eligible for
     *                           RoundRobinConstructor - callers (a later wiring phase, not this
     *                           class) are responsible for checking isEligible()/selecting a
     *                           different strategy first, exactly as they already must for
     *                           RoundRobinConstructor itself.
     */
    public function solve(
        array $rounds,
        array $activeTeams,
        array $activeVenues,
        GenerationConfig $config,
        int $timeBudgetMs = 10000,
    ): ExactSolverResult {
        $startedAt = microtime(true);
        $scorer = new ScheduleScorer;
        $constructor = new RoundRobinConstructor($this->rng);

        if (! $constructor->isEligible($activeTeams, $activeVenues)) {
            throw new \RuntimeException(
                'ExactSolver requires an eligible RoundRobinConstructor input (every active team owns a '.
                'distinct home venue, or exactly one venue is shared by exactly two teams). Check isEligible() '.
                'before calling solve() - this mirrors RoundRobinConstructor::construct()\'s own precondition.'
            );
        }

        if ($rounds === []) {
            $emptyCandidate = new ScheduleCandidate([]);

            return new ExactSolverResult(
                $emptyCandidate,
                $scorer->score($emptyCandidate, $activeTeams, $activeVenues, $config),
                provenOptimal: true,
                orderingsExplored: 0,
                elapsedMs: $this->elapsedMs($startedAt),
            );
        }

        // The seed is built and scored FIRST, before any search state is
        // set up, so the incumbent is always available from the very first
        // ordering considered (see class docblock - this is what makes
        // pruning effective immediately and what guarantees the result is
        // never worse than construction alone).
        $seedCandidate = $constructor->construct($rounds, $activeTeams, $activeVenues);
        $seedReport = $scorer->score($seedCandidate, $activeTeams, $activeVenues, $config);

        $this->activeTeams = $activeTeams;
        $this->activeVenues = $activeVenues;
        $this->config = $config;
        $this->scorer = $scorer;
        $this->rounds = array_values($rounds);
        $this->R = count($this->rounds);

        $n = count($activeTeams);
        $this->isOdd = $n % 2 === 1;
        $slotCount = $n + ($this->isOdd ? 1 : 0);
        $this->phantomSlot = $slotCount - 1;
        $this->numOrientations = 1 << intdiv($n, 2);

        $this->cycle = $constructor->buildSingleCycle($slotCount);
        $this->C = count($this->cycle);
        $this->slotTeams = $constructor->assignTeamsToSlots($activeTeams, $this->cycle);

        if ($this->isOdd) {
            $this->slotTeams[] = null;
        }

        $this->venueLookup = [];
        foreach ($activeVenues as $venue) {
            $this->venueLookup[$venue->id] = $venue;
        }

        $matchCount = $this->R * intdiv($n, 2);
        $venueWeight = $this->enabledWeight($config, 'consecutive_venue');
        $breaksWeight = $this->enabledWeight($config, 'home_away_break');
        $this->spacingWeight = $this->enabledWeight($config, 'full_cycle_spacing');
        $this->fullCycleGap = max(0, $n - 1);
        $this->spacingDivisor = max(1, $matchCount * max(1, $this->fullCycleGap));

        $this->precomputeCycleData();
        $this->buildTransitionTable(
            $venueWeight > 0.0 ? $venueWeight / max(1, 2 * $matchCount) : 0.0,
            $breaksWeight > 0.0 ? $breaksWeight / max(1, 2 * $matchCount) : 0.0,
        );

        $this->best = $seedCandidate;
        $this->bestReport = $seedReport;
        $this->bestScore = $seedReport->score;
        $this->timedOut = false;
        $this->orderingsExplored = 0;
        $this->deadline = microtime(true) + max(0, $timeBudgetMs) / 1000;

        $counts = array_fill(0, $this->C, intdiv($this->R, $this->C));
        $remainder = $this->R % $this->C;

        for ($i = 0; $i < $remainder; $i++) {
            $counts[$i]++;
        }

        $this->enumerate(0, $counts, array_fill(0, $this->R, 0));

        return new ExactSolverResult(
            $this->best,
            $this->bestReport,
            provenOptimal: ! $this->timedOut,
            orderingsExplored: $this->orderingsExplored,
            elapsedMs: $this->elapsedMs($startedAt),
        );
    }

    // --- Search state for the current solve() call ------------------------
    // Deliberately instance state rather than threaded through every
    // recursive call's parameter list: solve() is not reentrant (a single
    // call owns this state start to finish), and the alternative is a
    // dozen-plus parameters passed unchanged through every stack frame of
    // enumerate()/evaluateOrdering(). Every property here is (re)initialised
    // at the top of solve() before use.

    /** @var TeamInput[] */
    private array $activeTeams;

    /** @var VenueInput[] */
    private array $activeVenues;

    private GenerationConfig $config;

    private ScheduleScorer $scorer;

    /** @var RoundInput[] */
    private array $rounds;

    private int $R;

    private int $C;

    private bool $isOdd;

    private int $phantomSlot;

    private int $numOrientations;

    /** @var array<int, array{pairs: array<int, array{0: int, 1: int}>, role: array<int, int>}> */
    private array $cycle;

    /** @var array<int, TeamInput|null> */
    private array $slotTeams;

    /** @var array<int, VenueInput> */
    private array $venueLookup;

    private float $spacingWeight;

    private int $fullCycleGap;

    private int $spacingDivisor;

    /** @var array<int, int|null> cycle round index => the one slot on bye that round (null if activeTeamCount is even) */
    private array $byeSlotByCycle = [];

    /** @var array<int, array<int, array{0: int, 1: int}>> cycle round index => its real (non-phantom) pairs, in a stable order */
    private array $pairsOrderByCycle = [];

    /** @var array<int, array<int, int>> cycle round index => slot => that slot's opponent slot */
    private array $opponentOfByCycle = [];

    /** @var array<int, array<int, array<int, int>>> cycle round index => orientation index => slot => role (1 = home, 0 = away) */
    private array $roleArray = [];

    /** @var array<int, array<int, array<int, array<int, float>>>> prevCycleIndex => curCycleIndex => prevOrientation => curOrientation => weighted proxy transition cost */
    private array $transitionWeighted = [];

    private ScheduleCandidate $best;

    private GenerationReport $bestReport;

    private float $bestScore;

    private bool $timedOut = false;

    private int $orderingsExplored = 0;

    private float $deadline;

    /**
     * Precomputes, once per solve() call, everything about each cycle
     * round that doesn't depend on which OTHER cycle round it ends up
     * adjacent to in a given ordering: which slot is on bye (odd team
     * counts only), the stable-ordered list of real pairs (bit `i` of an
     * orientation index always means "pair `i` in this list", so the same
     * orientation index means the same thing every time this cycle round
     * is used, no matter where in the ordering it lands), each slot's
     * opponent, and the concrete home/away role array for every one of
     * the 2^k possible orientations of that round (k real pairs, each
     * independently flippable - this is NOT a single global flip choice,
     * see class docblock).
     */
    private function precomputeCycleData(): void
    {
        foreach ($this->cycle as $cycleIndex => $cycleRound) {
            $realPairs = [];
            $byeSlot = null;

            foreach ($cycleRound['pairs'] as [$a, $b]) {
                if ($this->isOdd && ($a === $this->phantomSlot || $b === $this->phantomSlot)) {
                    $byeSlot = $a === $this->phantomSlot ? $b : $a;

                    continue;
                }

                $realPairs[] = [$a, $b];
            }

            $this->byeSlotByCycle[$cycleIndex] = $byeSlot;
            $this->pairsOrderByCycle[$cycleIndex] = $realPairs;

            $opponentOf = [];
            foreach ($realPairs as [$a, $b]) {
                $opponentOf[$a] = $b;
                $opponentOf[$b] = $a;
            }
            $this->opponentOfByCycle[$cycleIndex] = $opponentOf;

            for ($o = 0; $o < $this->numOrientations; $o++) {
                $role = [];

                foreach ($realPairs as $i => [$a, $b]) {
                    $bit = ($o >> $i) & 1;
                    $role[$a] = $bit === 0 ? 1 : 0;
                    $role[$b] = $bit === 0 ? 0 : 1;
                }

                $this->roleArray[$cycleIndex][$o] = $role;
            }
        }
    }

    /**
     * Precomputes the weighted proxy transition cost for every (previous
     * cycle round, previous orientation, current cycle round, current
     * orientation) combination up front, so that every ordering's DP
     * (evaluateOrdering()) is pure table lookups. This is what keeps
     * tens of thousands of orderings inside the time budget - see class
     * docblock.
     *
     * A "venue incident" between two adjacent played rounds for one slot
     * is NOT simply "same role twice": home-home is always the same venue
     * (a team's own venue never changes), but away-away is a venue repeat
     * only if it was against the SAME opponent both times (a different
     * opponent means a different opponent's venue, since every team owns
     * a distinct venue in the eligible cases this solver runs on). A
     * "break" (home_away_break's unit) is simpler - any repeated role,
     * home or away, counts - so the two proxies are tracked separately
     * even though both only ever fire when role(t-1) === role(t).
     *
     * @param  float  $venueUnit  weight('consecutive_venue') / divisor, or 0.0 if that criterion isn't enabled
     * @param  float  $breaksUnit  weight('home_away_break') / divisor, or 0.0 if that criterion isn't enabled
     */
    private function buildTransitionTable(float $venueUnit, float $breaksUnit): void
    {
        $n = count($this->activeTeams);

        for ($cA = 0; $cA < $this->C; $cA++) {
            $byeA = $this->byeSlotByCycle[$cA];
            $opponentA = $this->opponentOfByCycle[$cA];

            for ($cB = 0; $cB < $this->C; $cB++) {
                $byeB = $this->byeSlotByCycle[$cB];
                $opponentB = $this->opponentOfByCycle[$cB];

                $commonSlots = [];
                for ($slot = 0; $slot < $n; $slot++) {
                    if ($slot === $byeA || $slot === $byeB) {
                        continue;
                    }

                    $commonSlots[] = $slot;
                }

                for ($oA = 0; $oA < $this->numOrientations; $oA++) {
                    $roleA = $this->roleArray[$cA][$oA];

                    for ($oB = 0; $oB < $this->numOrientations; $oB++) {
                        $roleB = $this->roleArray[$cB][$oB];

                        $venueCount = 0;
                        $breaksCount = 0;

                        foreach ($commonSlots as $slot) {
                            $ra = $roleA[$slot];
                            $rb = $roleB[$slot];

                            if ($ra !== $rb) {
                                continue;
                            }

                            $breaksCount++;

                            if ($ra === 1 || $opponentA[$slot] === $opponentB[$slot]) {
                                $venueCount++;
                            }
                        }

                        $this->transitionWeighted[$cA][$cB][$oA][$oB] = $venueUnit * $venueCount + $breaksUnit * $breaksCount;
                    }
                }
            }
        }
    }

    /**
     * Backtracks over multiset permutations of the cycle-round indices
     * (each used as close to equally-often as $this->R allows - see class
     * docblock), with position 0 always fixed to cycle round 0. $counts
     * and $ordering are passed by value deliberately: PHP's copy-on-write
     * means each recursive call gets an implicit snapshot, and mutating-
     * then-restoring the caller's own copy after the recursive call
     * returns is the standard, allocation-cheap way to backtrack without
     * an explicit clone.
     *
     * @param  array<int, int>  $counts  remaining uses of each cycle round index
     * @param  array<int, int>  $ordering  cycle round index chosen so far, by output round position
     */
    private function enumerate(int $position, array $counts, array $ordering): void
    {
        if ($this->timedOut) {
            return;
        }

        if (microtime(true) >= $this->deadline) {
            $this->timedOut = true;

            return;
        }

        if ($position === $this->R) {
            $this->evaluateOrdering($ordering);
            $this->orderingsExplored++;

            return;
        }

        for ($c = 0; $c < $this->C; $c++) {
            if ($counts[$c] === 0) {
                continue;
            }

            if ($position === 0 && $c !== 0) {
                continue;
            }

            $counts[$c]--;
            $ordering[$position] = $c;
            $this->enumerate($position + 1, $counts, $ordering);
            $counts[$c]++;

            if ($this->timedOut) {
                return;
            }
        }
    }

    /**
     * For one complete ordering: computes full_cycle_spacing exactly
     * (pairing-order-determined, no DP needed), solves the optimal
     * home/away orientation sequence by DP over the precomputed
     * transition table, combines both into a lower-bound weighted score,
     * and only materialises + really scores this ordering if that bound
     * beats the current incumbent (see class docblock's "CRITICAL
     * SUBTLETY" for why the bound is valid pruning but the DP's own choice
     * of orientation is not trusted as the final answer on its own).
     *
     * @param  array<int, int>  $ordering  cycle round index per output round, length $this->R
     */
    private function evaluateOrdering(array $ordering): void
    {
        $numO = $this->numOrientations;
        $dp = array_fill(0, $numO, 0.0);
        $backptr = [];
        $prevCycle = $ordering[0];

        for ($t = 1; $t < $this->R; $t++) {
            $curCycle = $ordering[$t];
            $table = $this->transitionWeighted[$prevCycle][$curCycle];

            $newDp = array_fill(0, $numO, INF);
            $newBack = array_fill(0, $numO, 0);

            for ($oPrev = 0; $oPrev < $numO; $oPrev++) {
                $base = $dp[$oPrev];

                if ($base === INF) {
                    continue;
                }

                $row = $table[$oPrev];

                for ($oCur = 0; $oCur < $numO; $oCur++) {
                    $cost = $base + $row[$oCur];

                    if ($cost < $newDp[$oCur]) {
                        $newDp[$oCur] = $cost;
                        $newBack[$oCur] = $oPrev;
                    }
                }
            }

            $dp = $newDp;
            $backptr[$t] = $newBack;
            $prevCycle = $curCycle;
        }

        $bestFinalOrientation = 0;
        $dpMinimal = $dp[0];

        for ($o = 1; $o < $numO; $o++) {
            if ($dp[$o] < $dpMinimal) {
                $dpMinimal = $dp[$o];
                $bestFinalOrientation = $o;
            }
        }

        $spacingRaw = $this->computeSpacingRaw($ordering);
        $lowerBound = $this->spacingWeight * ($spacingRaw / $this->spacingDivisor) + $dpMinimal;

        // Every tier weight is positive (GenerationConfig::tierWeight()),
        // and every criterion not represented in $lowerBound contributes
        // >= 0 to the real score, so this bound can never overstate what
        // any orientation of THIS ordering could achieve - safe to prune.
        if ($lowerBound >= $this->bestScore) {
            return;
        }

        $orientations = array_fill(0, $this->R, 0);
        $orientations[$this->R - 1] = $bestFinalOrientation;

        for ($t = $this->R - 1; $t >= 1; $t--) {
            $orientations[$t - 1] = $backptr[$t][$orientations[$t]];
        }

        $candidate = $this->buildCandidate($ordering, $orientations);
        $report = $this->scorer->score($candidate, $this->activeTeams, $this->activeVenues, $this->config);

        if ($report->score < $this->bestScore) {
            $this->best = $candidate;
            $this->bestReport = $report;
            $this->bestScore = $report->score;
        }
    }

    /**
     * The exact (not proxied) full_cycle_spacing shortfall total for one
     * ordering - independent of orientation, since it only cares about
     * which two slots meet and how many rounds apart, matching
     * FullCycleSpacingCriterion's own formula but keyed on slot pairs
     * (equivalent to team pairs, since $this->slotTeams is a fixed
     * bijection for the whole search).
     *
     * @param  array<int, int>  $ordering
     */
    private function computeSpacingRaw(array $ordering): float
    {
        $lastMeetingRound = [];
        $shortfallTotal = 0;

        foreach ($ordering as $t => $cycleIndex) {
            foreach ($this->pairsOrderByCycle[$cycleIndex] as [$a, $b]) {
                $key = $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";

                if (isset($lastMeetingRound[$key])) {
                    $gap = $t - $lastMeetingRound[$key];
                    $shortfallTotal += max(0, $this->fullCycleGap - $gap);
                }

                $lastMeetingRound[$key] = $t;
            }
        }

        return (float) $shortfallTotal;
    }

    /**
     * Materialises one (ordering, orientation sequence) pair into a real
     * ScheduleCandidate using $this->slotTeams (the single random team-to-
     * slot draw used throughout the whole search - see class docblock)
     * and $this->rounds (for each output round's real date/round id/match
     * slot ids).
     *
     * @param  array<int, int>  $ordering  cycle round index per output round
     * @param  array<int, int>  $orientations  orientation index per output round
     */
    private function buildCandidate(array $ordering, array $orientations): ScheduleCandidate
    {
        $roundCandidates = [];

        foreach ($ordering as $t => $cycleIndex) {
            $roundInput = $this->rounds[$t];

            $matchIdByVenueId = [];
            foreach ($roundInput->slots as $slot) {
                $matchIdByVenueId[$slot->venueId] = $slot->matchId;
            }

            $role = $this->roleArray[$cycleIndex][$orientations[$t]];
            $matches = [];
            $byeTeamIds = [];

            foreach ($this->cycle[$cycleIndex]['pairs'] as [$slotA, $slotB]) {
                if ($this->isOdd && ($slotA === $this->phantomSlot || $slotB === $this->phantomSlot)) {
                    $realSlot = $slotA === $this->phantomSlot ? $slotB : $slotA;
                    $byeTeamIds[] = $this->slotTeams[$realSlot]->id;

                    continue;
                }

                $teamA = $this->slotTeams[$slotA];
                $teamB = $this->slotTeams[$slotB];

                [$home, $away] = $role[$slotA] === 1 ? [$teamA, $teamB] : [$teamB, $teamA];

                $venue = $this->venueLookup[$home->homeVenueId];
                $matches[] = new MatchCandidate($home->homeVenueId, $venue->name, $home->id, $away->id, $matchIdByVenueId[$home->homeVenueId] ?? null);
            }

            $roundCandidates[] = new RoundCandidate($roundInput->date, $matches, $byeTeamIds, $roundInput->roundId);
        }

        return new ScheduleCandidate($roundCandidates);
    }

    /**
     * A key's ABSENCE from $config's soft criteria means "never scored"
     * (see GenerationConfig's own docblock), not merely deprioritised -
     * tierWeight() alone can't distinguish "weight 1.0 because it's the
     * lowest enabled tier" from "not enabled at all", so callers that need
     * that distinction (this solver's pruning bound and DP objective both
     * do - an unweighted criterion must contribute exactly 0, not some
     * fallback weight) have to check flatSoftCriteria() membership first.
     */
    private function enabledWeight(GenerationConfig $config, string $key): float
    {
        return in_array($key, $config->flatSoftCriteria(), true) ? $config->tierWeight($key) : 0.0;
    }

    private function elapsedMs(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }
}
