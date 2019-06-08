<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use App\Series;
use App\Venue;
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
            return view('association.home', ['association' => $this->association]);
        }
        else {
            abort(404);
        }
    }

    public function series(Association $association) {
        return view('association.series', ['association' => $association]);
    }

    public function divisions(Association $association) {
        return view('association.divisions', ['association' => $association]);
    }

    public function submitScore() {
        return view('association.home', ['association' => $this->association]);
    }

    public function standings() {
        return view('association.standings', ['association' => $this->association]);
    }

    public function schedule() {
        return view('association.schedule', ['association' => $this->association]);
    }

    public function css() {
        $response = \Response::make('body { background-color: red; }');
        $response->header('Content-Type', 'text/css');
        return $response;
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

        if (isset($request->home_image_file)) {
            $path = $request->home_image_file->storeAs(
                'home_image_file/' . $association->subdomain, $request->home_image_file->getClientOriginalName(), 'public'
            );

            $association->home_image_path = $path;
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
                'divisions' => Division::orderBy('sequence', 'ASC')->where('association_id', $association->id)->get(),
                'venues' => Venue::orderBy('name', 'ASC')->where('association_id', $association->id)->get(),
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
