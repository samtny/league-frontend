<?php

namespace App\Http\Controllers;

use App\Association;
use App\Round;
use App\Schedule;
use App\Series;
use Bouncer;
use Illuminate\Http\Request;

class SeriesController extends Controller
{

    public function view(Series $series) {
        return view('series.view', [
            'series' => $series,
        ]);
    }

    /**
     * Store a new series.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request) {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $series = new series;

        $series->name = $request->name;
        $series->user_id = $request->user_id;
        $series->association_id = $request->association_id;

        $series->save();

        return redirect()->route('association.series', ['association' => Association::find($request->association_id)]);
    }

    public function edit(Series $series) {
        return view('series.edit', [
            'current_user' => \Auth::user(),
            'series' => $series,
            //'start_date_string' => $series->start_date !== NULL ? date('Y-m-d', $series->start_date) : NULL,
            'start_date_string' => $series->start_date !== NULL ? date('Y-m-d', strtotime($series->start_date)) : NULL,
            //'end_date_string' => $series->end_date !== NULL ? date('Y-m-d', $series->end_date) : NULL,
            'end_date_string' => $series->end_date !== NULL ? date('Y-m-d', strtotime($series->end_date)) : NULL,
            'associations' => Association::where('user_id', \Auth::user()->id)->get(),
            'association_id' => $series->association_id,
            'schedules' => Schedule::where('series_id', $series->id)->orderBy('sequence', 'ASC')->orderBy('start_date', 'ASC')->get(),
        ]);
    }

    public function create() {
        return view('series.create', [
            'current_user' => \Auth::user(),
            'associations' => Association::where('user_id', \Auth::user()->id)->get(),
        ]);
    }

    public function update(Request $request) {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $series = series::find($request->id);

        $series->name = $request->name;
        //$series->user_id = $request->user_id;

        if (isset($request->association_id)) {
            $series->association_id = $request->association_id;
        }

        if ($request->start_date !== NULL) {
            //$start_date_timestamp = strtotime($request->start_date);
            //$series->start_date = $start_date_timestamp;
            $series->start_date = $request->start_date;
        }
        else {
            $series->start_date = NULL;
        }

        if ($request->end_date !== NULL) {
            //$end_date_timestamp = strtotime($request->end_date);
            //$series->end_date = $end_date_timestamp;
            $series->end_date = $request->end_date;
        }
        else {
            $series->end_date = NULL;
        }

        $series->save();

        $request->session()->flash('message', __('Successfully updated series :series!', ['series' => $series->name]));

        return redirect()->route('series.view', ['series' => $series]);
    }

    public function schedules(Series $series) {
        return view('series.schedules', ['series' => $series]);
    }

}
