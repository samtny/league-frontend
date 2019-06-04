<?php

namespace App\Http\Controllers;

use App\Association;
use App\Series;
use Bouncer;
use Illuminate\Http\Request;

class AssociationsController extends Controller
{

    public function __construct(Request $request) {
        $subdomain = array_first(explode('.', \Request::getHost()));

        $this->association = Association::where('subdomain', $subdomain)->first();
    }

    public function home() {
        if (!empty($this->association)) {
            return view('association.view', ['association' => $this->association]);
        }
        else {
            abort(404);
        }
    }

    /**
     * Store a new association.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request) {
        if (Bouncer::can('create', Association::class)) {
            $association = new Association;

            $association->name = $request->name;
            $association->user_id = $request->user_id;

            $association->save();

            // TODO: Do not necessarily "onboard" for certain roles?
            return redirect()->route('onboard.association', ['association' => $association]);
        }
        else {
            return view('denied');
        }
    }

    public function update(Request $request) {

        $association = Association::find($request->id);

        $association->name = $request->name;
        $association->user_id = $request->user_id;

        if (isset($request->subdomain)) {
            $association->subdomain = $request->subdomain;
        }

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
        if (Bouncer::can('create', Association::class)) {
            return view('association.create', ['current_user' => \Auth::user()]);
        }
        else {
            return view('denied');
        }
    }

    public function deleteConfirm(Association $association) {
        return view('association.delete', ['association' => $association]);
    }

    public function delete(Association $association) {
        $association->delete();

        return redirect()->route('user', ['user' => \Auth::user()])->with('success', 'Association deleted successfully.');
    }

    public function undeleteConfirm(Association $association) {
        return view('association.undelete', ['association' => $association]);
    }

    public function undelete(Association $association) {
        $association->restore();

        return redirect()->route('user', ['user' => \Auth::user()])->with('success', 'Association restored successfully.');
    }

    public function view(Association $association) {
        return view('association.view', ['association' => $association]);
    }

}
