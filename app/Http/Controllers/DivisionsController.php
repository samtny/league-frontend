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
            $validatedData = $request->validate([
                'name' => 'required|max:255',
            ]);

            $division = new Division;

            $division->name = $request->name;
            $division->sequence = $request->sequence;
            $division->association_id = $association->id;

            $division->save();

            return redirect()->route('association.divisions', ['association' => $association]);
        }
        else {
            return view('denied');
        }
    }

    public function update(Association $association, Division $division, Request $request) {
        if (Bouncer::can('update', Division::class)) {
            $validatedData = $request->validate([
                'name' => 'required|max:255',
            ]);

            $division->name = $request->name;
            $division->sequence = $request->sequence;
            $division->association_id = $association->id;

            $division->save();

            return redirect()->route('association.divisions', ['association' => $association]);
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

    public function deleteConfirm(Association $association, Division $division) {
        if (Bouncer::can('delete', $division)) {
            return view('division.delete', ['division' => $division]);
        }
        else {
            return view('denied');
        }
    }

    public function delete(Association $association, Division $division) {
        if (Bouncer::can('delete', $division)) {
            $division->delete();

            return redirect()->route('association.divisions', ['association' => $association])->with('success', 'Division deleted successfully.');
        }
        else {
            return view('denied');
        }
    }

}
