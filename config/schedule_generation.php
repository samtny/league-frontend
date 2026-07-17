<?php

return [
    // max_attempts bounds simulated-annealing iterations (a single cheap
    // local move + rescore each), not full-schedule randomized restarts.
    'max_attempts' => env('SCHEDULE_GENERATION_MAX_ATTEMPTS', 10000),
    'time_budget_ms' => env('SCHEDULE_GENERATION_TIME_BUDGET_MS', 2000),

    // System-wide default priority order for the 7 soft criteria, highest
    // priority first. Converted into a dominance-scaled ("big-M") weight per
    // criterion by GenerationConfig::tierWeight() - a one-unit improvement in
    // a higher-ranked criterion always outweighs the maximum possible sum of
    // every lower-ranked criterion combined. An Association can override this
    // ordering via its schedule_generation_settings.priority column; see
    // GenerationConfig::forAssociation().
    'default_priority' => [
        'equal_matches_played',
        'home_away_balance',
        'home_venue_balance',
        'repeat_opponent_consecutive_rounds',
        'opponent_recency',
        'home_away_break',
        'consecutive_venue',
    ],
];
