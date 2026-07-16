<?php

return [
    'max_attempts' => env('SCHEDULE_GENERATION_MAX_ATTEMPTS', 10000),
    'time_budget_ms' => env('SCHEDULE_GENERATION_TIME_BUDGET_MS', 1500),

    'weights' => [
        'venue' => 1.0,
        'equality' => 1.0,
        'repeat' => 1.0,
        'home_away' => 1.0,
        'home_venue_balance' => 1.0,
        'home_away_break' => 1.0,
        'consecutive_opponent_repeat' => 1.0,
    ],
];
