<?php

namespace App\Http\Controllers;

use App\Association;
use App\Venue;
use Bouncer;
use Illuminate\Http\Request;

class VenuesController extends Controller
{

    public function create(Association $association) {
        return view('venue.create', [
            'association' => $association,
        ]);
    }

    public function store(Association $association, Request $request) {
        if (Bouncer::can('create', Venue::class)) {
            $venue = new venue;

            $venue->name = $request->name;
            $venue->association_id = $association->id;

            $venue->save();

            if (!empty($request->url)) {
                return redirect($request->url)->with('success', 'Data saved successfully!');
            }

            return redirect()->route('user', ['id' => \Auth::user()->id]);
        }
        else {
            return view('denied');
        }
    }

}
