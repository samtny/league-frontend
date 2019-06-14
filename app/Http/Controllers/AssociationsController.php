<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use App\Match;
use App\Result;
use App\ResultSubmission;
use App\Round;
use App\Series;
use App\Schedule;
use App\Venue;
use Bouncer;
use Illuminate\Http\Request;

class AssociationsController extends Controller
{

    public function __construct(Request $request) {
        $subdomain = array_first(explode('.', \Request::getHost()));

        $this->association = Association::where('subdomain', $subdomain)->first();
    }

    public function view(Association $association) {
        return view('association.view', ['association' => $association]);
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

    public function home() {
        if (!empty($this->association)) {
            return view('association.home', ['association' => $this->association]);
        }
        else {
            abort(404);
        }
    }

    public function divisions(Association $association) {
        return view('association.divisions', ['association' => $association]);
    }

    public function teams(Association $association) {
        return view('association.teams', ['association' => $association]);
    }

    public function venues(Association $association) {
        return view('association.venues', ['association' => $association]);
    }

    public function series(Association $association) {
        return view('association.series', ['association' => $association]);
    }

    public function submitScoreBegin(Request $request) {
        if (!empty($this->association)) {
            // get schedules with start_date < today, end_date > today
            $schedules = $this->association->schedules
            ->where('start_date', '<', date('Y-m-d', strtotime('today')))
            ->where('end_date', '>', date('Y-m-d', strtotime('today')));

            // get rounds with start_date < today, but greater than today - 1 week
            $rounds = Round::whereIn('schedule_id', $schedules->pluck('id'))
                ->where('start_date','>=', date('Y-m-d', strtotime('-1 week')))
                ->where('start_date', '<=', date('Y-m-d', strtotime("today")))->get();

            $divisions = Division::whereIn('id', $rounds->pluck('division_id'))->get();

            if (count($divisions) === 1) {
                $request->division_id = $divisions[0]->id;

                return $this->submitScoreStep2($request);
            }
            else {
                return view('forms.results.choose-division', [
                    'association' => $this->association,
                    'divisions' => $divisions,
                    ]);
            }
        }
        else {
            abort(404);
        }
    }

    public function submitScoreStep2(Request $request) {
        if (!empty($this->association)) {
            $division = Division::find($request->division_id);

            // get schedules with start_date < today, end_date > today, matching division
            $schedules = $this->association->schedules
                ->where('start_date', '<=', date('Y-m-d', strtotime('today')))
                ->where('end_date', '>=', date('Y-m-d', strtotime('today')))
                ->where('division_id', $division->id);

            // get rounds with start_date < today, but greater than today - 1 week
            $rounds = Round::whereIn('schedule_id', $schedules->pluck('id'))
                ->where('start_date','>=', date('Y-m-d', strtotime('-1 week')))
                ->where('start_date', '<=', date('Y-m-d', strtotime("today")))
                ->orderBy('start_date', 'DESC')
                ->get();

            return view('forms.results.choose-match', [
                'association' => $this->association,
                'rounds' => $rounds,
                ]);
        }
        else {
            abort(404);
        }
    }

    public function submitScoreStep3(Request $request) {
        if (!empty($this->association)) {
            $match = Match::find($request->match_id);

            return view('forms.results.input-scores', [
                'association' => $this->association,
                'match' => $match,
                ]);
        }
        else {
            abort(404);
        }
    }

    public function submitScoreStep4(Request $request) {
        if (!empty($this->association)) {
            $match_id = $request->match_id;

            if (!empty($match_id)) {
                $home_team_id = $request->home_team_id;
                $away_team_id = $request->away_team_id;
                $home_team_score = $request->home_team_score;
                $away_team_score = $request->away_team_score;

                $submission = new ResultSubmission();
                $submission->association_id = $this->association->id;
                $submission->schedule_id = Match::find($match_id)->schedule_id;
                $submission->match_id = $match_id;
                $submission->home_team_score = $home_team_score;
                $submission->away_team_score = $away_team_score;

                $submission->save();

                return view('forms.results.thanks', [
                    'association' => $this->association,
                    ]);
            }
            else {
                abort(404);
            }
        }
        else {
            abort(404);
        }
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

        return redirect()->route('admin')->with('success', 'Association deleted successfully.');
    }

    public function undeleteConfirm(Association $association) {
        return view('association.undelete', ['association' => $association]);
    }

    public function undelete(Association $association) {
        $association->restore();

        return redirect()->route('user', ['user' => \Auth::user()])->with('success', 'Association restored successfully.');
    }



}
