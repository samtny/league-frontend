<?php

namespace App\Http\Controllers;

use App\Association;
use App\Schedule;

class ResultsController extends Controller
{
    public function edit(Association $association, Schedule $schedule)
    {
        return view('results.edit', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }
}
