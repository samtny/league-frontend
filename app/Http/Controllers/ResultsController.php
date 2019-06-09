<?php

namespace App\Http\Controllers;

use App\Schedule;
use Illuminate\Http\Request;

class ResultsController extends Controller
{
    public function edit(Schedule $schedule) {
        return view('results.edit', [
            'schedule' => $schedule,
        ]);
    }
}
