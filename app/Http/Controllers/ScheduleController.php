<?php

namespace App\Http\Controllers;

use App\Series;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{

    public function create(Series $series) {
        $available_series = \App\Series::where(['association_id' => $series->association_id])->get()->all();
        $available_divisions = \App\Division::orderBy('sequence' , 'ASC')->where(['association_id' => $series->association_id])->get()->all();

        return view('schedule.create', [
            'series' => $series,
            'available_series' => $available_series,
            'available_divisions' => $available_divisions,
        ]);
    }

}
