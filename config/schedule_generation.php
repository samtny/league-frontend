<?php

return [
    'max_attempts' => env('SCHEDULE_GENERATION_MAX_ATTEMPTS', 500),
    'time_budget_ms' => env('SCHEDULE_GENERATION_TIME_BUDGET_MS', 1500),

    'weights' => [
        'venue' => 5.0,
        'equality' => 8.0,
        'repeat' => 3.0,
        'home_away' => 2.0,
        'home_venue_balance' => 6.0,
    ],
];
