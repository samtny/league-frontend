<?php

namespace App\Http\Controllers;

use App\Association;
use App\Result;
use App\ResultSubmission;
use App\TeamResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResultSubmissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Association $association)
    {
        return view('result_submissions.approve', ['association' => $association]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Association $association, Request $request, $id)
    {
        $submission = ResultSubmission::find($id);

        if ($request->delete == 'delete') {
            $submission->delete();
        } else {
            $result = Result::where('match_id', $submission->match_id)->first();

            if (empty($result)) {
                $result = new Result;
                $result->match_id = $submission->match_id;
            }

            $result->home_team_id = $submission->match->home_team_id;
            $result->away_team_id = $submission->match->away_team_id;
            $result->home_team_score = $submission->home_team_score;
            $result->away_team_score = $submission->away_team_score;

            $result->save();

            // home team result:
            $team_result = TeamResult::where('schedule_id', $submission->match->schedule_id)
                ->where('match_id', $submission->match_id)
                ->where('team_id', $result->home_team_id)
                ->first();

            if (empty($team_result)) {
                $team_result = new TeamResult;
                $team_result->schedule_id = $submission->match->schedule_id;
                $team_result->match_id = $submission->match->id;
                $team_result->team_id = $result->home_team_id;
            }

            $team_result->points = $result->home_team_score;
            $team_result->win = $result->home_team_id == $submission->win_team_id ? 1 : 0;
            $team_result->loss = $result->home_team_id != $submission->win_team_id ? 1 : 0;
            $team_result->tie = 0;
            $team_result->save();

            // away team result;
            $team_result = TeamResult::where('schedule_id', $submission->match->schedule_id)
                ->where('match_id', $submission->match_id)
                ->where('team_id', $result->away_team_id)
                ->first();

            if (empty($team_result)) {
                $team_result = new TeamResult;
                $team_result->schedule_id = $submission->match->schedule_id;
                $team_result->match_id = $submission->match->id;
                $team_result->team_id = $result->away_team_id;
            }

            $team_result->points = $result->away_team_score;
            $team_result->win = $result->away_team_id == $submission->win_team_id ? 1 : 0;
            $team_result->loss = $result->away_team_id != $submission->win_team_id ? 1 : 0;
            $team_result->tie = 0;
            $team_result->save();

            $submission->approved = true;
            $submission->save();
        }

        $url = $request->url;

        if (! empty($url)) {
            return redirect($url)->with('success', 'Data saved successfully!');
        }

        return redirect()->route('user', ['user' => \Auth::user()->id]);
    }
}
