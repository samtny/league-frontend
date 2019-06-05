<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use Bouncer;
use Illuminate\Http\Request;

class DivisionsController extends Controller
{

    public function create(Association $association) {
        return view('division.create', [
            'association' => $association,
        ]);
    }

    public function store(Association $association, Request $request) {
        if (Bouncer::can('create', Division::class)) {
            $division = new Division;

            $division->name = $request->name;
            $division->sequence = $request->sequence;
            $division->association_id = $association->id;

            $division->save();

            if (!empty($request->url)) {
                return redirect($request->url)->with('success', 'Data saved successfully!');
            }

            return redirect()->route('user', ['id' => \Auth::user()->id]);
        }
        else {
            return view('denied');
        }
    }

    public function update(Association $association, Division $division, Request $request) {
        if (Bouncer::can('update', Division::class)) {
            $division->name = $request->name;
            $division->sequence = $request->sequence;
            $division->association_id = $association->id;

            $division->save();

            if (!empty($request->url)) {
                return redirect($request->url)->with('success', 'Data saved successfully!');
            }

            return redirect()->route('user', ['id' => \Auth::user()->id]);
        }
        else {
            return view('denied');
        }
    }

    public function edit(Association $association, Division $division) {
        if (Bouncer::can('edit', $division)) {
            return view('division.edit', [
                'association' => $association,
                'division' => $division,
            ]);
        }
        else {
            return view('denied');
        }
    }

}
