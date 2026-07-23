<?php

namespace App\Http\Controllers;

use App\Association;
use App\Schedule;
use App\Series;
use Illuminate\Http\Request;

class SeriesController extends Controller
{
    public function index(Association $association)
    {
        return view('association.series', ['association' => $association]);
    }

    public function archived(Association $association)
    {
        return view('association.series_archived', ['association' => $association]);
    }

    public function view(Association $association, Series $series)
    {
        return view('series.view', [
            'association' => $association,
            'series' => $series,
        ]);
    }

    /**
     * Store a new series.
     *
     * @return Response
     */
    public function store(Association $association, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $series = new Series;

        $series->name = $request->name;
        $series->user_id = \Auth::user()->id;
        $series->association_id = $association->id;

        $series->save();

        return redirect()->route('association.series', ['association' => $association]);
    }

    public function edit(Association $association, Series $series)
    {
        return view('series.edit', [
            'association' => $association,
            'current_user' => \Auth::user(),
            'series' => $series,
            'schedules' => Schedule::where('series_id', $series->id)->orderBy('sequence', 'ASC')->orderBy('start_date', 'ASC')->get(),
        ]);
    }

    public function create(Association $association)
    {
        return view('series.create', [
            'association' => $association,
            'current_user' => \Auth::user(),
        ]);
    }

    public function update(Association $association, Series $series, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $series->name = $request->name;
        $series->archived = $request->has('archived');

        $series->save();

        $request->session()->flash('message', __('Successfully updated series :series!', ['series' => $series->name]));

        return redirect()->route('series.view', ['association' => $association, 'series' => $series]);
    }

    public function schedulesArchived(Association $association, Series $series)
    {
        return view('series.schedules_archived', ['association' => $association, 'series' => $series]);
    }

    public function deleteConfirm(Association $association, Series $series)
    {
        return view('series.delete', ['association' => $association, 'series' => $series]);
    }

    public function delete(Association $association, Series $series)
    {
        $series->delete();

        return redirect()->route('association.series', ['association' => $association])->with('success', 'Series deleted successfully.');
    }
}
