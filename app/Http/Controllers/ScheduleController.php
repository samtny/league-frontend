<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Schedule;
use App\Series;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function view(Association $association, Schedule $schedule)
    {
        return view('schedule.view', ['association' => $association, 'schedule' => $schedule]);
    }

    public function create(Association $association, Series $series)
    {
        $available_series = Series::where(['association_id' => $association->id])->get()->all();
        $available_divisions = Division::orderBy('sequence', 'ASC')->where(['association_id' => $association->id])->get()->all();

        return view('schedule.create', [
            'association' => $association,
            'series' => $series,
            'available_series' => $available_series,
            'available_divisions' => $available_divisions,
        ]);
    }

    public function edit(Association $association, Schedule $schedule)
    {
        return view('schedule.edit', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    public function rounds(Association $association, Schedule $schedule)
    {
        return view('schedule.rounds', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    public function store(Association $association, Series $series, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $name = $request->name;
        $division_id = $request->division_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $weekday = $request->weekday;
        $generate = $request->generate;

        $schedule = new Schedule;

        $schedule->name = $name;
        $schedule->association_id = $association->id;
        $schedule->series_id = $series->id;
        $schedule->division_id = $division_id;
        $schedule->start_date = $start_date;
        $schedule->end_date = $end_date;

        $division = Division::where(['id' => $division_id])->first();

        if (! empty($division)) {
            $schedule->name = $division->name;
            $schedule->sequence = $division->sequence;
        } else {
            $schedule->name = $schedule->start_date;
            $schedule->sequence = null;
        }

        $schedule->save();

        if ($generate) {
            $this->generateRounds($start_date, $end_date, $weekday, $schedule);
        }

        return redirect()->route('series.schedules', ['association' => $association, 'series' => $series]);
    }

    public function update(Association $association, Schedule $schedule, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $name = $request->name;
        $division_id = $request->division_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $weekday = $request->weekday;
        $generate = $request->generate;

        $schedule->name = $name;
        $schedule->division_id = $division_id;
        $schedule->start_date = $start_date;
        $schedule->end_date = $end_date;
        $schedule->archived = $request->archived;

        $schedule->save();

        if ($generate) {
            $this->truncateRounds($schedule);
            $this->generateRounds($start_date, $end_date, $weekday, $schedule);
        }

        $request->session()->flash('message', __('Successfully updated schedule'));

        return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);

    }

    private function generateRounds($start_date, $end_date, $weekday, $schedule)
    {
        $start_datetime = new \DateTime($start_date);
        $end_datetime = new \DateTime($end_date);

        $days_interval = $start_datetime->diff($end_datetime);

        $days = $days_interval->format('%a');

        $round_number = 1;

        for ($i = 0; $i <= $days; $i += 1) {
            if (strtolower($start_datetime->format('D')) == strtolower($weekday)) {
                $round = new Round;

                $round->schedule_id = $schedule->id;
                $round->division_id = $schedule->division_id;
                $round->series_id = $schedule->series_id;

                $round->start_date = $start_datetime;
                $round->end_date = $start_datetime;
                $round->name = 'Round '.$round_number;

                $round->save();

                $round_number += 1;

                $round->createMatches();
            }

            $start_datetime->add(new \DateInterval('P1D'));
        }
    }

    private function truncateRounds(Schedule $schedule)
    {
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
