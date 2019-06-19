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
            $validatedData = $request->validate([
                'name' => 'required|max:255',
            ]);

            $venue = new venue;

            $venue->name = $request->name;
            $venue->association_id = $association->id;

            $venue->save();

            return redirect()->route('association.venues', ['association' => $association]);
        }
        else {
            return view('denied');
        }
    }

    public function edit(Association $association, Venue $venue) {
        if (Bouncer::can('edit', Venue::class)) {
            return view('venue.edit', ['venue' => $venue]);
        }
        else {
            return view('denied');
        }
    }

    public function update(Venue $venue, Request $request) {
        if (Bouncer::can('update', Venue::class)) {
            $validatedData = $request->validate([
                'name' => 'required|max:255',
            ]);

            $venue = Venue::where(['id' => $venue->id])->first();

            $venue->name = $request->name;

            $venue->save();

            return redirect()->route('association.venues', ['association' => $venue->association]);
        }
        else {
            return view('denied');
        }
    }

    public function deleteConfirm(Venue $venue) {
        if (Bouncer::can('delete', $venue)) {
            return view('venue.delete', ['venue' => $venue]);
        }
        else {
            return view('denied');
        }
    }

    public function delete(Venue $venue) {
        if (Bouncer::can('delete', $venue)) {
            $association = $venue->association;

            $venue->delete();

            return redirect()->route('association.venues', ['association' => $association])->with('success', 'Venue deleted successfully.');
        }
        else {
            return view('denied');
        }
    }

}
