<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Schedule;
use App\Series;
use Illuminate\Http\Request;
use Illuminate\Logging\Log;

class ScheduleController extends Controller
{

    public function view(Schedule $schedule) {
        return view('schedule.view', ['schedule' => $schedule]);
    }

    public function create(Series $series) {
        $association_id = $series->association_id;
        $available_series = \App\Series::where(['association_id' => $series->association_id])->get()->all();
        $available_divisions = \App\Division::orderBy('sequence' , 'ASC')->where(['association_id' => $series->association_id])->get()->all();

        return view('schedule.create', [
            'association_id' => $association_id,
            'series' => $series,
            'available_series' => $available_series,
            'available_divisions' => $available_divisions,
        ]);
    }

    public function edit(Association $association, Schedule $schedule) {
        return view('schedule.edit', [
            'association' => $schedule->association,
            'schedule' => $schedule,
        ]);
    }

    public function rounds(Schedule $schedule) {
        return view('schedule.rounds', [
            'schedule' => $schedule,
        ]);
    }

    public function store(Series $series, Request $request) {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $name = $request->name;
        $association_id = $series->association->id;
        $division_id = $request->division_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $weekday = $request->weekday;
        $archived = $request->archived;
        $generate = $request->generate;

        $schedule = new Schedule;

        $schedule->name = $name;
        $schedule->association_id = $association_id;
        $schedule->series_id = $series->id;
        $schedule->division_id = $division_id;
        $schedule->start_date = $start_date;
        $schedule->end_date = $end_date;

        $division = Division::where(['id' => $division_id])->first();

        if (!empty($division)) {
            $schedule->name = $division->name;
            $schedule->sequence = $division->sequence;
        }
        else {
            $schedule->name = $schedule->start_date;
            $schedule->sequence = null;
        }

        $schedule->save();

        if ($generate) {
            $this->generateRounds($association_id, $start_date, $end_date, $weekday, $schedule);
        }

        return redirect()->route('series.schedules', ['series' => $series]);
    }

    public function update(Request $request) {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $schedule = Schedule::find($request->id);

        $name = $request->name;
        $association_id = $schedule->association_id;
        $division_id = $request->division_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $weekday = $request->weekday;
        $archived = $request->archived;
        $generate = $request->generate;

        $schedule->name = $name;
        $schedule->division_id = $division_id;
        $schedule->start_date = $start_date;
        $schedule->end_date = $end_date;
        $schedule->archived = $archived;

        $schedule->save();

        if ($generate) {
            $this->truncateRounds($schedule);
            $this->generateRounds($association_id, $start_date, $end_date, $weekday, $schedule);
        }

        $request->session()->flash('message', __('Successfully updated schedule'));

        return redirect()->route('schedule.view', ['schedule' => $schedule]);

    }

    private function generateRounds($association_id, $start_date, $end_date, $weekday, $schedule) {
        $series = Series::where(['id' => $schedule->series_id])->first();

        $start_datetime = new \DateTime($start_date);
        $end_datetime = new \DateTime($end_date);

        $days_interval = $start_datetime->diff($end_datetime);

        $days = $days_interval->format('%a');

        $association = Association::where(['id' => $association_id])->first();
        $venues = $association->venues;

        $round_number = 1;

        for ($i = 0; $i <= $days; $i += 1) {
            if (strtolower($start_datetime->format('D')) == strtolower($weekday)) {
                $round = new Round;

                $round->schedule_id = $schedule->id;
                $round->division_id = $schedule->division_id;
                $round->series_id = $schedule->series_id;

                $round->start_date = $start_datetime;
                $round->end_date = $start_datetime;
                $round->name = 'Round ' . $round_number;

                $round->save();

                $round_number += 1;

                foreach ($venues as $venue) {
                    $match = new PLMatch;

                    $match->name = $venue->name . ' – ' . $round->start_date->format('m-d-Y');
                    $match->association_id = $association_id;
                    $match->series_id = $schedule->series_id;
                    $match->division_id = $schedule->division_id;

                    // Unique key fields:
                    $match->schedule_id = $schedule->id;
                    $match->round_id = $round->id;
                    $match->venue_id = $venue->id;
                    $match->sequence = 1;

                    $match->start_date = $round->start_date;
                    $match->end_date = $round->end_date;

                    $match->save();
                }
            }

            $start_datetime->add(new \DateInterval('P1D'));
        }
    }

    private function truncateRounds(Schedule $schedule) {
        $rounds = Round::where(['schedule_id' => $schedule->id])->get();

        foreach ($rounds as $round) {
            $matches = PLMatch::where(['round_id' => $round->id])->get();

            foreach ($matches as $match) {
                $match->delete();
            }

            $round->delete();
        }
    }

}
