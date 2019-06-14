<?php

namespace App\Http\Controllers;

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
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
        $round = Round::find($id);

        foreach ($round->matches as $match) {
            $home_team_id = $request->{'match_' . $match->id . '__home_team_id'};
            $away_team_id = $request->{'match_' . $match->id . '__away_team_id'};

            $match->home_team_id = $home_team_id;
            $match->away_team_id = $away_team_id;

            $match->save();
        }

        $request->session()->flash('message', __('Successfully updated round'));

        $url = $request->url;

        if (!empty($url)) {
            return redirect($url)->with('success', __('Data saved successfully!'));
        }

        return redirect()->route('user', ['id' => \Auth::user()->id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
