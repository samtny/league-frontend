<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use App\Round;
use App\Schedule;
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

    public function edit(Schedule $schedule) {
        return view('schedule.edit', [
            'schedule' => $schedule,
            'available_series' => \App\Series::where(['association_id' => $schedule->association_id])->get()->all(),
            'available_divisions' => \App\Division::orderBy('sequence' , 'ASC')->where(['association_id' => $schedule->association_id])->get()->all(),
        ]);
    }

    public function store(Series $series, Request $request) {

        $division_id = $request->division_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $weekday = $request->weekday;

        $schedule = new Schedule;

        $schedule->series_id = $series->id;
        $schedule->division_id = $division_id;
        $schedule->start_date = $start_date;
        $schedule->end_date = $end_date;

        $division = Division::where(['id' => $division_id])->first();

        if (!empty($division)) {
            $schedule->sequence = $division->sequence;
        }
        else {
            $schedule->sequence = null;
        }

        $schedule->save();

        $start_datetime = new \DateTime($start_date);
        $end_datetime = new \DateTime($end_date);

        $days_interval = $start_datetime->diff($end_datetime);

        $days = $days_interval->format('%a');

        $round_number = 1;

        for ($i = 0; $i <= $days; $i += 1) {
            if (strtolower($start_datetime->format('D')) == strtolower($weekday)) {
                echo $start_datetime->format('Y-m-d') . "\n";

                $round = new Round;

                $round->schedule_id = $schedule->id;
                $round->division_id = $division_id;
                $round->series_id = $series->id;
                $round->start_date = $start_datetime;
                $round->end_date = $start_datetime;
                $round->name = 'Round ' . $round_number;

                $round->save();

                $round_number += 1;
            }

            $start_datetime->add(new \DateInterval('P1D'));
        }

        if (!empty($url)) {
            return redirect($url)->with('success', __('Schedule saved successfully!'));
        }

        return redirect()->route('user', ['id' => \Auth::user()->id]);
    }

}
