<?php

namespace App\Http\Controllers;

use App\Association;
use App\Round;
use App\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoundsController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Association $association, Schedule $schedule)
    {
        return view('round.create', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Association $association, Schedule $schedule, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'start_date' => 'required|date',
        ]);

        $round = new Round;

        $round->name = $request->name;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->schedule_id = $schedule->id;
        $round->start_date = $request->start_date;
        $round->end_date = $request->end_date;

        $round->save();

        $round->createMatches();

        return redirect()->route('schedule.rounds', ['association' => $association, 'schedule' => $schedule]);
    }

    public function edit(Association $association, Schedule $schedule, Round $round)
    {
        return view('round.edit', [
            'association' => $association,
            'schedule' => $schedule,
            'round' => $round,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(Association $association, Schedule $schedule, Round $round, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'start_date' => 'required|date',
        ]);

        $round->name = $request->name;
        $round->start_date = $request->start_date;
        $round->end_date = $request->end_date;
        $round->scores_closed = isset($request->scores_closed);

        $round->save();

        foreach ($round->matches as $match) {
            $home_team_id = $request->{'match_'.$match->id.'__home_team_id'};
            $away_team_id = $request->{'match_'.$match->id.'__away_team_id'};

            $match->home_team_id = $home_team_id;
            $match->away_team_id = $away_team_id;

            $match->save();
        }

        $request->session()->flash('message', __('Successfully updated round'));

        return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);
    }

    public function deleteConfirm(Association $association, Schedule $schedule, Round $round)
    {
        return view('round.delete-confirm', [
            'association' => $association,
            'schedule' => $schedule,
            'round' => $round,
        ]);
    }

    public function destroy(Association $association, Schedule $schedule, Round $round)
    {
        $round->delete();

        return redirect()->route('schedule.rounds', ['association' => $association, 'schedule' => $schedule]);
    }
}
