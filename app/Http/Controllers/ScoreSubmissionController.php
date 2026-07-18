<?php

namespace App\Http\Controllers;

use App\Division;
use App\PLMatch;
use App\ResultSubmission;
use App\Round;
use Illuminate\Http\Request;

class ScoreSubmissionController extends AssociationAwareController
{
    public function step1(Request $request)
    {
        if (! empty($this->association)) {
            // get schedules with start_date < today, end_date > today, not archived
            $schedules = $this->association->schedules
                ->where('start_date', '<=', date('Y-m-d', strtotime('today')))
                ->where('end_date', '>=', date('Y-m-d', strtotime('today')))
                ->filter(fn ($schedule) => $schedule->archived != 1);

            // get rounds with start_date < today, but greater than today - 1 week
            $rounds = Round::whereIn('schedule_id', $schedules->pluck('id'))
                ->where('start_date', '>=', date('Y-m-d', strtotime('-1 week')))
                ->where('start_date', '<=', date('Y-m-d', strtotime('today')))->get();

            $divisions = Division::whereIn('id', $rounds->pluck('division_id'))->get();

            if (count($divisions) === 1) {
                $request->division_id = $divisions[0]->id;

                return $this->step2($request);
            } else {
                return view('forms.results.choose-division', [
                    'association' => $this->association,
                    'divisions' => $divisions,
                ]);
            }
        } else {
            abort(404);
        }
    }

    public function step2(Request $request)
    {
        if (! empty($this->association)) {
            $division = Division::find($request->division_id);

            // get schedules with start_date < today, end_date > today, matching division, not archived
            $schedules = $this->association->schedules
                ->where('start_date', '<=', date('Y-m-d', strtotime('today')))
                ->where('end_date', '>=', date('Y-m-d', strtotime('today')))
                ->where('division_id', $division->id)
                ->filter(fn ($schedule) => $schedule->archived != 1);

            // get rounds with start_date < today, but greater than today - 1 week, not closed
            $rounds = Round::whereIn('schedule_id', $schedules->pluck('id'))
                ->where('start_date', '>=', date('Y-m-d', strtotime('-1 week')))
                ->where('start_date', '<=', date('Y-m-d', strtotime('today')))
                ->where(function ($query) {
                    $query->where('scores_closed', 0);
                    $query->orWhereNull('scores_closed');
                })
                ->orderBy('start_date', 'DESC')
                ->get();

            return view('forms.results.choose-match', [
                'association' => $this->association,
                'rounds' => $rounds,
            ]);
        } else {
            abort(404);
        }
    }

    public function step3(Request $request)
    {
        if (! empty($this->association)) {
            $match = PLMatch::find($request->match_id);

            return view('forms.results.input-scores', [
                'association' => $this->association,
                'match' => $match,
            ]);
        } else {
            abort(404);
        }
    }

    public function step4(Request $request)
    {
        if (! empty($this->association)) {
            $match_id = $request->match_id;

            if (! empty($match_id)) {
                $home_team_id = $request->home_team_id;
                $away_team_id = $request->away_team_id;
                $home_team_score = $request->home_team_score;
                $away_team_score = $request->away_team_score;

                $submission = new ResultSubmission;
                $submission->association_id = $this->association->id;
                $submission->schedule_id = PLMatch::find($match_id)->schedule_id;
                $submission->match_id = $match_id;
                $submission->home_team_score = $home_team_score;
                $submission->away_team_score = $away_team_score;
                $submission->save();

                if ($home_team_score != $away_team_score) {
                    $submission->win_team_id = $home_team_score > $away_team_score ? $home_team_id : $away_team_id;
                    $submission->save();

                    return view('forms.results.thanks', [
                        'association' => $this->association,
                    ]);
                } else {
                    return view('forms.results.choose-winner', [
                        'association' => $this->association,
                        'match' => PLMatch::find($submission->match_id),
                        'submission' => $submission,
                    ]);
                }
            } else {
                abort(404);
            }
        } else {
            abort(404);
        }
    }

    public function step5(Request $request)
    {
        if (! empty($this->association)) {
            $submission_id = $request->submission_id;

            if (! empty($submission_id)) {
                $submission = ResultSubmission::find($submission_id);

                $submission->win_team_id = $request->win_team_id;

                $submission->save();

                return view('forms.results.thanks', [
                    'association' => $this->association,
                ]);
            } else {
                abort(404);
            }
        } else {
            abort(404);
        }
    }
}
