<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Schedule;
use App\Series;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function view(Association $association, Schedule $schedule)
    {
        return view('schedule.view', ['association' => $association, 'schedule' => $schedule]);
    }

    public function create(Association $association, Series $series)
    {
        $available_series = Series::where(['association_id' => $association->id])->get()->all();
        $available_divisions = Division::orderBy('sequence', 'ASC')->where(['association_id' => $association->id])->get()->all();

        return view('schedule.create', [
            'association' => $association,
            'series' => $series,
            'available_series' => $available_series,
            'available_divisions' => $available_divisions,
        ]);
    }

    public function edit(Association $association, Schedule $schedule)
    {
        return view('schedule.edit', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    public function rounds(Association $association, Schedule $schedule)
    {
        return view('schedule.rounds', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    public function deleteConfirm(Association $association, Schedule $schedule)
    {
        return view('schedule.delete-confirm', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    public function destroy(Association $association, Schedule $schedule)
    {
        $series = $schedule->series;

        $schedule->delete();

        return redirect()->route('series.schedules', ['association' => $association, 'series' => $series]);
    }

    public function store(Association $association, Series $series, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $name = $request->name;
        $division_id = $request->division_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $weekday = $request->weekday;
        $generate = $request->generate;

        $schedule = new Schedule;

        $schedule->name = $name;
        $schedule->association_id = $association->id;
        $schedule->series_id = $series->id;
        $schedule->division_id = $division_id;
        $schedule->start_date = $start_date;
        $schedule->end_date = $end_date;
        $schedule->weekday = $weekday;

        $division = Division::where(['id' => $division_id])->first();

        $schedule->sequence = ! empty($division) ? $division->sequence : null;

        $schedule->save();

        if ($generate === 'manual') {
            $this->createRoundsManual($start_date, $end_date, $weekday, $schedule);
        } elseif ($generate === 'random') {
            $this->createRoundsRandom($start_date, $end_date, $weekday, $schedule);
        }

        return redirect()->route('series.schedules', ['association' => $association, 'series' => $series]);
    }

    public function update(Association $association, Schedule $schedule, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
        ]);

        $schedule->name = $request->name;
        $schedule->division_id = $request->division_id;
        $schedule->start_date = $request->start_date;
        $schedule->end_date = $request->end_date;
        $schedule->weekday = $request->weekday;
        $schedule->archived = $request->archived;

        $schedule->save();

        $request->session()->flash('message', __('Successfully updated schedule'));

        return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);

    }

    /**
     * Entry point for the Generate Rounds wizard. If the schedule is missing
     * a field round generation depends on (start/end date, weekday), that's
     * caught here before any destructive step is even offered - createRounds*
     * fails silently (e.g. strtolower(null) never matches a weekday) rather
     * than throwing, so without this check a user could delete their
     * existing rounds and end up with none at all.  If rounds already exist,
     * the user must confirm deleting them first; otherwise they go straight
     * to choosing an assignment method.
     */
    public function generateRounds(Association $association, Schedule $schedule)
    {
        $missingFields = $this->scheduleGenerationErrors($schedule);

        if (! empty($missingFields)) {
            return view('schedule.generate-rounds-invalid', [
                'association' => $association,
                'schedule' => $schedule,
                'missingFields' => $missingFields,
            ]);
        }

        if ($schedule->rounds->isNotEmpty()) {
            return view('schedule.generate-rounds-confirm', [
                'association' => $association,
                'schedule' => $schedule,
            ]);
        }

        return view('schedule.generate-rounds-select', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    public function generateRoundsDelete(Association $association, Schedule $schedule)
    {
        if (! empty($this->scheduleGenerationErrors($schedule))) {
            return redirect()->route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]);
        }

        $this->truncateRounds($schedule);

        return redirect()->route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]);
    }

    public function generateRoundsStore(Association $association, Schedule $schedule, Request $request)
    {
        $request->validate([
            'generate' => 'required|in:manual,random',
        ]);

        if (! empty($this->scheduleGenerationErrors($schedule))) {
            return redirect()->route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]);
        }

        $this->truncateRounds($schedule);

        if ($request->generate === 'manual') {
            $this->createRoundsManual($schedule->start_date, $schedule->end_date, $schedule->weekday, $schedule);
        } else {
            $this->createRoundsRandom($schedule->start_date, $schedule->end_date, $schedule->weekday, $schedule);
        }

        return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);
    }

    /**
     * Fields round generation reads directly off the Schedule model (not
     * re-collected in the wizard). Returns the human-readable labels of
     * whichever are missing, empty if the schedule is ready to generate.
     */
    private function scheduleGenerationErrors(Schedule $schedule): array
    {
        $missing = [];

        if (empty($schedule->start_date)) {
            $missing[] = 'Start Date';
        }

        if (empty($schedule->end_date)) {
            $missing[] = 'End Date';
        }

        if (empty($schedule->weekday)) {
            $missing[] = 'Match Weekday';
        }

        return $missing;
    }

    private function createRoundsManual($start_date, $end_date, $weekday, $schedule)
    {
        $start_datetime = new \DateTime($start_date);
        $end_datetime = new \DateTime($end_date);

        $days_interval = $start_datetime->diff($end_datetime);

        $days = $days_interval->format('%a');

        $round_number = 1;

        for ($i = 0; $i <= $days; $i += 1) {
            if (strtolower($start_datetime->format('D')) == strtolower($weekday)) {
                $round = new Round;

                $round->schedule_id = $schedule->id;
                $round->division_id = $schedule->division_id;
                $round->series_id = $schedule->series_id;

                $round->start_date = $start_datetime;
                $round->end_date = $start_datetime;
                $round->name = 'Round '.$round_number;

                $round->save();

                $round_number += 1;

                $round->createMatches();
            }

            $start_datetime->add(new \DateInterval('P1D'));
        }
    }

    private function createRoundsRandom($start_date, $end_date, $weekday, $schedule)
    {
        // TODO: automatic random assignment not yet implemented.
    }

    private function truncateRounds(Schedule $schedule)
    {
        $rounds = Round::where(['schedule_id' => $schedule->id])->get();

        foreach ($rounds as $round) {
            $matches = PLMatch::where(['round_id' => $round->id])->get();

            foreach ($matches as $match) {
                $match->delete();
            }

            $round->delete();
        }
    }
}
