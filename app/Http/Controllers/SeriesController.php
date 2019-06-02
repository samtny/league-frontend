<?php

namespace App\Http\Controllers;

use App\Series;
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

}
