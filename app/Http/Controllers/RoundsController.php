<?php

namespace App\Http\Controllers;

use App\Match;
use App\Round;
use App\Schedule;
use Illuminate\Http\Request;

class RoundsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Schedule $schedule)
    {
        return view('round.create', [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Schedule $schedule)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'start_date' => 'required|date',
        ]);

        $round = new Round();

        $round->name = $request->name;
        $round->series_id = $schedule->series->id;
        $round->schedule_id = $schedule->id;
        $round->start_date = $request->start_date;
        $round->end_date = $request->end_date;

        $round->save();

        $association = $schedule->association;
        $venues = $association->venues;

        foreach ($venues as $venue) {
            $match = new Match;

            $match->name = $venue->name . ' – ' . $round->start_date->format('m-d-Y');
            $match->association_id = $association->id;

            if (!empty($schedule->series)) {
                $match->series_id = $schedule->series->id;

                if (!empty($schedule->series->division)) {
                    $match->division_id = $schedule->series->division->id;
                }
            }

            // Unique key fields:
            $match->schedule_id = $schedule->id;
            $match->round_id = $round->id;
            $match->venue_id = $venue->id;
            $match->sequence = 1;

            $match->start_date = $round->start_date;
            $match->end_date = $round->end_date;

            $match->save();
        }

        return redirect()->route('schedule.rounds', ['schedule' => $schedule]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    public function edit(Schedule $schedule, Round $round)
    {
        return view('round.edit', [
            'schedule' => $schedule,
            'round' => $round,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param \App\Schedule $schedule
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $schedule, $id)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'start_date' => 'required|date',
        ]);

        $round = Round::find($id);

        $round->name = $request->name;
        $round->start_date = $request->start_date;
        $round->end_date = $request->end_date;
        $round->scores_closed = isset($request->scores_closed);

        $round->save();

        foreach ($round->matches as $match) {
            $home_team_id = $request->{'match_' . $match->id . '__home_team_id'};
            $away_team_id = $request->{'match_' . $match->id . '__away_team_id'};

            $match->home_team_id = $home_team_id;
            $match->away_team_id = $away_team_id;

            $match->save();
        }

        $request->session()->flash('message', __('Successfully updated round'));

        return redirect()->route('schedule.rounds', ['schedule' => $schedule]);
    }

    public function deleteConfirm(Schedule $schedule, Round $round) {
        return view('round.delete-confirm', [
            'schedule' => $schedule,
            'round' => $round,
        ]);
    }

    public function destroy(Schedule $schedule, Round $round)
    {
        $round = Round::find($round->id);

        if (!empty($round)) {
            $round->delete();
        }

        return redirect()->route('schedule.rounds', ['schedule' => $schedule]);
    }
}
