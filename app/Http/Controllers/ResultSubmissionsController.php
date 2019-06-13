<?php

namespace App\Http\Controllers;

use App\Association;
use App\Result;
use App\ResultSubmission;
use Illuminate\Http\Request;

class ResultSubmissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Association $association)
    {
        return view('result_submissions.approve', ['association' => $association]);
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

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $submission = ResultSubmission::find($id);

        if ($request->delete == 'delete') {
            $submission->delete();
        }
        else {
            $result = Result::where('match_id', $submission->match_id)->first();

            if (empty($result)) {
                $result = new Result();
                $result->match_id = $submission->match_id;
            }

            $result->home_team_id = $submission->match->home_team_id;
            $result->away_team_id = $submission->match->away_team_id;
            $result->home_team_score = $submission->home_team_score;
            $result->away_team_score = $submission->away_team_score;

            $result->save();

            $submission->approved = TRUE;
            $submission->save();
        }

        $url = $request->url;

        if (!empty($url)) {
            return redirect($url)->with('success', 'Data saved successfully!');
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
