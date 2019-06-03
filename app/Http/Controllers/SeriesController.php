<?php

namespace App\Http\Controllers;

use App\Association;
use App\Series;
use Bouncer;
use Illuminate\Http\Request;

class SeriesController extends Controller
{

    /**
     * Store a new series.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request) {

        $series = new series;

        $series->name = $request->name;
        $series->user_id = $request->user_id;
        $series->association_id = $request->association_id;

        $series->save();

        // TODO: Do not necessarily "onboard" for certain roles?
        return redirect()->route('onboard.series', ['series' => $series]);

    }

    public function edit(Series $series) {
        if (Bouncer::can('edit', $series)) {
            return view('series.edit', [
                'current_user' => \Auth::user(),
                'series' => $series,
                'associations' => Association::where('user_id', \Auth::user()->id)->get(),
                'association_id' => $series->association_id
            ]);
        }
        else {
            return view('denied');
        }
    }

    public function create() {
        return view('series.create', [
            'current_user' => \Auth::user(),
            'associations' => Association::where('user_id', \Auth::user()->id)->get(),
        ]);
    }

    public function update(Request $request) {

        $series = series::find($request->id);

        $series->name = $request->name;
        $series->user_id = $request->user_id;
        $series->association_id = $request->association_id;

        $series->save();

        //Session::flash('message', 'Successfully updated nerd!');

        $url = $request->url;

        if (!empty($url)) {
            return redirect($url)->with('success', 'Data saved successfully!');
        }

        return redirect()->route('user', ['id' => \Auth::user()->id]);

    }

    public function view(Series $series) {
        return view('series.view', [
            'series' => $series,
        ]);
    }

}
