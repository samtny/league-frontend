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

    public function inactive(Association $association)
    {
        return view('association.venues_inactive', ['association' => $association]);
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
                'pinballmap_id' => 'nullable|string|max:255',
            ]);

            $venue = new Venue;

            $venue->name = $request->name;
            $venue->association_id = $association->id;
            $venue->pinballmap_id = $request->pinballmap_id;
            $venue->active = $request->boolean('active');

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
                'pinballmap_id' => 'nullable|string|max:255',
                'division_ids' => 'array',
                'division_ids.*' => 'integer|exists:divisions,id',
            ]);

            $venue->name = $request->name;
            $venue->pinballmap_id = $request->pinballmap_id;
            $venue->active = $request->boolean('active');

            $venue->save();

            $venue->divisions()->sync($request->input('division_ids', []));

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
