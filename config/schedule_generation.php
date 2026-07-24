<?php

return [
    // max_attempts is a defensive CEILING on simulated-annealing iterations
    // (a single cheap local move + rescore each), not the primary driver of
    // how much searching happens - see search_epochs below for that. Only
    // binds when the epoch-scaled budget would exceed it, which by design
    // (see search_epochs) happens right around a 16-team/16-venue/10-round
    // schedule and up - LARGER league sizes are explicitly OUT OF SCOPE for
    // now, they just get capped here rather than scaling further.
    'max_attempts' => env('SCHEDULE_GENERATION_MAX_ATTEMPTS', 100000),
    // time_budget_ms is a flat wall-clock ceiling, deliberately NOT scaled
    // by league size - generation runs synchronously inside an HTTP
    // request, so a bigger league shouldn't mean a slower page load.
    'time_budget_ms' => env('SCHEDULE_GENERATION_TIME_BUDGET_MS', 2000),
    // search_epochs is the real attempts driver: EpsilonConstraintOptimizer
    // targets this many full sweeps of the schedule's own neighborhood
    // (SlotCount::total($rounds), roughly rounds x active venues - team
    // count itself doesn't affect slot count), so total search effort
    // scales with problem size instead of being a flat number regardless of
    // league size. Calibrated so a 16-team/16-venue/10-round schedule
    // (slot count 160) lands its scaled budget exactly on max_attempts's
    // default (625 x 160 = 100000) - smaller schedules scale down from
    // there, larger ones are OUT OF SCOPE for now and simply get capped at
    // max_attempts instead of receiving additional headroom. If that scope
    // ever expands, revisit this value and max_attempts together. See
    // GenerationConfig::DEFAULT_SEARCH_EPOCHS.
    'search_epochs' => env('SCHEDULE_GENERATION_SEARCH_EPOCHS', 625),

    // exact_solver_time_budget_ms is ExactSolver's OWN wall-clock ceiling
    // (GenerationStrategy::Exact, plan.md "Size-Aware Schedule Generation"
    // §6/§10 Phase 4b) - deliberately separate from time_budget_ms above,
    // since the exact solver is a fundamentally different, more expensive
    // search than the annealing passes that key bounds, and product signed
    // off on a much larger default for it alone (plan.md decision 2.4). On
    // expiry the solver returns the best schedule found so far, honestly
    // labelled as not proven optimal - it never refuses and never returns
    // worse than the seed. See GenerationConfig::$exactSolverTimeBudgetMs.
    'exact_solver_time_budget_ms' => env('SCHEDULE_GENERATION_EXACT_SOLVER_TIME_BUDGET_MS', 10000),

    // System-wide default list of which soft criteria are evaluated, and in
    // what priority order (highest priority first). A criterion runs ONLY if
    // its key appears somewhere in this list - omitting a key disables it
    // entirely: it is never scored, contributes no messages to the review
    // screen, and has no effect on the search. May cover any subset of the
    // known soft-criterion keys (see SoftCriterionRegistry), including an
    // empty array to run hard constraints only.
    //
    // Each element is EITHER a bare string key (a single criterion holding
    // that rank alone - the common case) OR an array of 2+ string keys (a
    // "tie-group" of co-equal criteria sharing that rank), e.g.:
    //   ['home_cycle_spacing', 'away_cycle_spacing'],  // tied - see below
    //   'equal_matches_played',                        // singleton
    // A singleton key is converted into a dominance-scaled ("big-M") weight
    // by GenerationConfig::tierWeight() - a one-unit improvement in a
    // higher-ranked tier always outweighs the maximum possible sum of every
    // lower-ranked tier combined. A tie-group's members share that SAME
    // weight (equally ranked against each other) but are NOT simply summed
    // together - EpsilonConstraintOptimizer resolves them jointly via
    // ChebyshevTieBreak (minimax on normalized regret from each member's own
    // best-achievable value), so improving one can't come at the other's
    // expense past the point where the other becomes the bottleneck. See
    // ChebyshevTieBreak's own docblock for the reasoning and a documented
    // future alternative (goal programming).
    //
    // An Association can override this list via its
    // schedule_generation_settings.soft_criteria column; see
    // GenerationConfig::forAssociation(). An invalid override (not an array;
    // an unknown key; a duplicate key, whether within one tie-group or across
    // two different tiers; or a tier element that's neither a string nor a
    // non-empty array of strings) falls back silently to this default.
    //
    // consecutive_venue outranks full_cycle_spacing. This is not arbitrary:
    // exhaustive enumeration of every valid 4-team/6-week double round-robin
    // proved that "full-cycle rematch spacing AND no 3-week same-venue run"
    // has ZERO solutions at that size - the two goals are mutually exclusive
    // there, so SOME ranking has to lose, and full_cycle_spacing ranked
    // above consecutive_venue mathematically forces a team to play three
    // weeks running at its own venue. consecutive_venue is the criterion
    // that actually expresses "change of scenery" (an adjacent rematch with
    // reversed home/away is fine; three weeks at the same venue is not), so
    // it wins the tie. See plan.md ("Size-Aware Schedule Generation") §1a
    // for the full enumeration and §3 for the decision.
    //
    // home_away_break is kept, but honestly: for a league where every team
    // has its own exclusive home venue (the common case), home_away_break's
    // raw penalty is exactly TWICE consecutive_venue's - "home" only ever
    // means "at my own venue" there, so a home/home break and a same-venue
    // repeat are the same event counted twice, and away/away breaks (two
    // DIFFERENT venues) are harmless noise padding the other half. In that
    // world this tier changes no ranking decision consecutive_venue hasn't
    // already made. It earns its keep only on the greedy path (RoundBuilder),
    // where a match can land at a venue neither team owns and "home" stops
    // being synonymous with "own venue" - there the two criteria genuinely
    // diverge. See plan.md §1b.
    // rematch_home_away_reversal and home_away_balance are enabled, and that
    // is NOT optional decoration - leaving them off is an exploitable hole,
    // found by running the shipped default end to end rather than by
    // reasoning. ExactSolver optimises exactly what it is given and nothing
    // else, so with those two absent it happily returned a 4-team/6-week
    // schedule that was genuinely optimal on the enabled criteria while
    // scheduling the SAME fixture twice with identical home/away (one team
    // hosting the other twice, never travelling to them) and leaving a team
    // on 2 home / 4 away. Both are things a human scheduler would call
    // broken. Measured across sizes, adding them costs nothing anywhere and
    // fixes the multi-cycle cases outright:
    //
    //   shape    venue repeats      max home/away gap    unreversed rematches
    //   4x6      1 -> 1 (same)      2 -> 0               5 -> 0
    //   7x6      0 -> 0 (same)      1 -> 1 (same)        0 -> 0
    //   14x10    5 -> 5 (same)      2 -> 2 (same)        0 -> 0
    //   16x10    5 -> 5 (same)      2 -> 2 (same)        0 -> 0
    //   16x20   16 -> 16 (same)     0 -> 0 (same)       16 -> 0
    //
    // The single-cycle shapes are unchanged because they have no rematches
    // to reverse in the first place. The general lesson, which applies to
    // any future edit of this list: an exhaustive search treats every
    // omitted criterion as licence, where the annealer merely got lucky by
    // starting from a structurally sound seed. See plan.md §1e.
    'soft_criteria' => [
        'consecutive_venue',
        'rematch_home_away_reversal',
        'home_away_balance',
        'full_cycle_spacing',
        'home_away_break',
        // 'balanced_opponents', // now a HARD constraint, see enforce_balanced_opponents below
        // 'repeat_opponent_consecutive_rounds',
        // ['home_cycle_spacing', 'away_cycle_spacing'],
        // 'equal_matches_played',
        // 'home_venue_balance',
    ],

    // Whether ScheduleScorer registers BalancedOpponentMeetingsConstraint (a
    // HARD constraint: every unordered pair of active teams must meet
    // between floor(M/P) and ceil(M/P) times, M = total matches, P = number
    // of pairs) - see GenerationConfig::$enforceBalancedOpponents for the
    // full rationale. Defaults true: every seed-based strategy
    // (RoundRobinConstructor, and eventually the exact solver) satisfies
    // this by construction, so turning it on costs those paths nothing. Only
    // the greedy fallback path (RoundBuilder, used when venue ownership data
    // makes seed construction ineligible) is at risk of violating it - that
    // path is expected to run with this OFF at the call site rather than by
    // changing this system-wide default, so a bad greedy result surfaces as
    // a review-screen warning instead of turning into a silent hard-invalid
    // regression. See plan.md ("Size-Aware Schedule Generation") §4.
    'enforce_balanced_opponents' => env('SCHEDULE_GENERATION_ENFORCE_BALANCED_OPPONENTS', true),
];
