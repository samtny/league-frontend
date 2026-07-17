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
    'soft_criteria' => [
        ['home_cycle_spacing', 'away_cycle_spacing'],
        'home_away_break',
        'balanced_opponents',
        'repeat_opponent_consecutive_rounds',
        'rematch_home_away_reversal',
        'consecutive_venue',
        'home_away_balance', # seems already solved / overlap w / less good than home_away_break
        #'full_cycle_spacing',
        #'equal_matches_played',
        #'home_venue_balance',
    ],
];
