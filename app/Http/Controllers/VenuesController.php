<?php

namespace App\Http\Controllers;

use App\Association;
use App\Venue;
use Bouncer;
use Illuminate\Http\Request;

class VenuesController extends Controller
{
    public function index(Association $association)
    {
        return view('association.venues', ['association' => $association]);
    }

    public function create(Association $association)
    {
        return view('venue.create', [
            'association' => $association,
        ]);
    }

    public function store(Association $association, Request $request)
    {
        if (Bouncer::can('create', Venue::class)) {
            $validatedData = $request->validate([
                'name' => 'required|max:255',
            ]);

            $venue = new Venue;

            $venue->name = $request->name;
            $venue->association_id = $association->id;

            $venue->save();

            return redirect()->route('association.venues', ['association' => $association]);
        } else {
            return view('denied');
        }
    }

    public function edit(Association $association, Venue $venue)
    {
        if (Bouncer::can('edit', Venue::class)) {
            return view('venue.edit', ['association' => $association, 'venue' => $venue]);
        } else {
            return view('denied');
        }
    }

    public function update(Association $association, Venue $venue, Request $request)
    {
        if (Bouncer::can('update', Venue::class)) {
            $validatedData = $request->validate([
                'name' => 'required|max:255',
            ]);

            $venue->name = $request->name;

            $venue->save();

            return redirect()->route('association.venues', ['association' => $association]);
        } else {
            return view('denied');
        }
    }

    public function deleteConfirm(Association $association, Venue $venue)
    {
        if (Bouncer::can('delete', $venue)) {
            return view('venue.delete', ['association' => $association, 'venue' => $venue]);
        } else {
            return view('denied');
        }
    }

    public function delete(Association $association, Venue $venue)
    {
        if (Bouncer::can('delete', $venue)) {
            $venue->delete();

            return redirect()->route('association.venues', ['association' => $association])->with('success', 'Venue deleted successfully.');
        } else {
            return view('denied');
        }
    }
}
