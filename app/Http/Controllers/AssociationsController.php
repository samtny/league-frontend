<?php

namespace App\Http\Controllers;

use App\Association;
use App\Series;
use Bouncer;
use Illuminate\Http\Request;

class AssociationsController extends Controller
{

    /**
     * Store a new association.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request) {

        $association = new Association;

        $association->name = $request->name;
        $association->user_id = $request->user_id;

        $association->save();

        // TODO: Do not necessarily "onboard" for certain roles?
        return redirect()->route('onboard.association', ['association' => $association]);

    }

    public function update(Request $request) {

        $association = Association::find($request->id);

        $association->name = $request->name;
        $association->user_id = $request->user_id;

        $association->save();

        //Session::flash('message', 'Successfully updated nerd!');

        $url = $request->url;

        if (!empty($url)) {
            return redirect($url)->with('success', 'Data saved successfully!');
        }

        return redirect()->route('user', ['id' => \Auth::user()->id]);

    }

    public function edit(Association $association) {
        if (Bouncer::can('edit', $association)) {
            return view('association.edit', [
                'association' => $association,
                'series' => Series::where('association_id', $association->id)->get(),
                'current_user' => \Auth::user()
            ]);
        }
        else {
            return view('denied');
        }
    }

    public function create() {
        return view('association.create', ['current_user' => \Auth::user()]);
    }

    public function view(Association $association) {
        return view('association.view', ['association' => $association]);
    }

}
