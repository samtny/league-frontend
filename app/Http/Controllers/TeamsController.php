<?php

namespace App\Http\Controllers;

use App\Association;
use App\Team;
use Bouncer;
use Illuminate\Http\Request;

class TeamsController extends Controller
{

    public function create(Association $association) {
        return view('team.create', [
            'association' => $association,
        ]);
    }

    public function store(Association $association, Request $request) {
        if (Bouncer::can('create', Team::class)) {
            $team = new team;

            $team->name = $request->name;
            $team->association_id = $association->id;
            $team->venue_id = !empty($request->venue_id) ? $request->venue_id : null;

            $team->save();

            if (!empty($request->url)) {
                return redirect($request->url)->with('success', 'Data saved successfully!');
            }

            return redirect()->route('user', ['id' => \Auth::user()->id]);
        }
        else {
            return view('denied');
        }
    }

    public function update(Association $association, Team $team, Request $request) {
        if (Bouncer::can('update', Team::class)) {
            $team = Team::where(['id' => $team->id])->first();

            $team->name = $request->name;

            $team->save();

            if (!empty($request->url)) {
                return redirect($request->url)->with('success', 'Data saved successfully!');
            }

            return redirect()->route('user', ['id' => \Auth::user()->id]);
        }
        else {
            return view('denied');
        }
    }

    public function edit(Association $association, Team $team) {
        return view('team.edit', ['team' => $team]);
    }

    public function deleteConfirm(Association $association, Team $team) {
        if (Bouncer::can('delete', $team)) {
            return view('team.delete', ['team' => $team]);
        }
        else {
            return view('denied');
        }
    }

    public function delete(Association $association, Team $team) {
        if (Bouncer::can('delete', $team)) {
            $team->delete();

            return redirect()->route('association.teams', ['association' => $association])->with('success', 'Team deleted successfully.');
        }
        else {
            return view('denied');
        }
    }

}
