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

    public function store(Series $series, Request $request) {

        $division_id = $request->division_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $weekday = $request->weekday;

        var_dump($division_id);
        var_dump($start_date);
        var_dump($end_date);
        var_dump($weekday);

        exit(1);

        $series = new series;

        $series->name = $request->name;
        $series->user_id = $request->user_id;
        $series->association_id = $request->association_id;

        $series->save();

        // TODO: Do not necessarily "onboard" for certain roles?
        return redirect()->route('onboard.series', ['series' => $series]);

    }

}
