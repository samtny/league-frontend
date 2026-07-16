<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Schedule;
use App\Series;
use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\GenerationReport;
use App\Services\ScheduleGeneration\GenerationResult;
use App\Services\ScheduleGeneration\RoundDatePlanner;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\ScheduleGenerator;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $division_id = $request->division_id;

        $schedule = new Schedule;

        $schedule->name = $request->name;
        $schedule->association_id = $association->id;
        $schedule->series_id = $series->id;
        $schedule->division_id = $division_id;
        $schedule->start_date = $request->start_date;
        $schedule->end_date = $request->end_date;
        $schedule->weekday = $request->weekday;

        $division = Division::where(['id' => $division_id])->first();

        $schedule->sequence = ! empty($division) ? $division->sequence : null;

        $schedule->save();

        // Same as editing a schedule: if start/end/weekday are all present,
        // Rounds are generated to match right away - no separate "Generate
        // Schedule" step during creation.
        $this->regenerateRounds($schedule);

        return redirect()->route('series.schedules', ['association' => $association, 'series' => $series]);
    }

    public function update(Association $association, Schedule $schedule, Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
        ]);

        $pendingValues = [
            'name' => $request->name,
            'division_id' => $request->division_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'weekday' => $request->weekday,
            'archived' => $request->archived,
        ];

        $needsRegeneration = $this->roundsNeedRegeneration($schedule, $pendingValues['start_date'], $pendingValues['end_date'], $pendingValues['weekday']);

        if ($schedule->rounds->isNotEmpty() && $needsRegeneration) {
            session([$this->sessionKey($schedule, 'update') => $pendingValues]);

            return redirect()->route('schedule.update.confirm', ['association' => $association, 'schedule' => $schedule]);
        }

        $this->applyScheduleUpdate($schedule, $pendingValues);

        if ($needsRegeneration) {
            $this->regenerateRounds($schedule);
        }

        $request->session()->flash('message', __('Successfully updated schedule'));

        return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);
    }

    /**
     * Shown instead of saving directly when Rounds already exist for this
     * Schedule but don't match the submitted weekday/start/end date - saving
     * as-is would leave them stale. The pending form values were stashed in
     * the session by update() above.
     */
    public function updateConfirm(Association $association, Schedule $schedule)
    {
        if (session($this->sessionKey($schedule, 'update')) === null) {
            return redirect()->route('schedule.edit', ['association' => $association, 'schedule' => $schedule]);
        }

        return view('schedule.update-confirm', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Commits the pending edit stashed by update(): saves the Schedule, then
     * deletes and regenerates the (empty) Rounds to match its new
     * weekday/start/end date.
     */
    public function updateConfirmAccept(Association $association, Schedule $schedule, Request $request)
    {
        $pendingValues = session($this->sessionKey($schedule, 'update'));

        if ($pendingValues === null) {
            return redirect()->route('schedule.edit', ['association' => $association, 'schedule' => $schedule]);
        }

        $this->applyScheduleUpdate($schedule, $pendingValues);
        $this->regenerateRounds($schedule);

        session()->forget($this->sessionKey($schedule, 'update'));

        $request->session()->flash('message', __('Successfully updated schedule and regenerated rounds'));

        return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);
    }

    private function applyScheduleUpdate(Schedule $schedule, array $values): void
    {
        $schedule->name = $values['name'];
        $schedule->division_id = $values['division_id'];
        $schedule->start_date = $values['start_date'];
        $schedule->end_date = $values['end_date'];
        $schedule->weekday = $values['weekday'];
        $schedule->archived = $values['archived'];

        $schedule->save();
    }

    /**
     * True when the round dates implied by the submitted weekday/start/end
     * date don't match the dates of the Rounds that already exist for this
     * schedule - i.e. leaving Rounds as-is would make them stale relative to
     * the Schedule. Blank fields never trigger regeneration on their own,
     * since there's nothing to generate from (mirrors scheduleGenerationErrors).
     */
    private function roundsNeedRegeneration(Schedule $schedule, $start_date, $end_date, $weekday): bool
    {
        if (empty($start_date) || empty($end_date) || empty($weekday)) {
            return false;
        }

        $existingDates = $schedule->rounds->pluck('start_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->sort()->values()->all();

        $expectedDates = collect((new RoundDatePlanner)->datesFor($start_date, $end_date, $weekday))
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->sort()->values()->all();

        return $existingDates !== $expectedDates;
    }

    /**
     * Deletes and rebuilds this schedule's Rounds (and their empty
     * placeholder Matches) from its own persisted weekday/start/end date.
     * No-op past the truncate if those fields aren't all present.
     */
    private function regenerateRounds(Schedule $schedule): void
    {
        $this->truncateRounds($schedule);

        if (empty($this->scheduleGenerationErrors($schedule))) {
            $this->createRoundsManual($schedule->start_date, $schedule->end_date, $schedule->weekday, $schedule);
        }
    }

    /**
     * Entry point for the Generate Matches wizard. If the schedule is
     * missing a field generation depends on (start/end date, weekday),
     * that's caught here before either option is even offered -
     * createRoundsManual/generateAutomaticCandidate fail silently (e.g.
     * strtolower(null) never matches a weekday) rather than throwing.
     * Otherwise always goes straight to the Clear/Automatic choice: Clear
     * only nulls out home_team_id/away_team_id (see clearMatchAssignments()),
     * so it doesn't need a separate destructive-action confirmation step.
     */
    public function generateMatches(Association $association, Schedule $schedule)
    {
        $missingFields = $this->scheduleGenerationErrors($schedule);

        if (! empty($missingFields)) {
            return view('schedule.generate-matches-invalid', [
                'association' => $association,
                'schedule' => $schedule,
                'missingFields' => $missingFields,
            ]);
        }

        return view('schedule.generate-matches-select', [
            'association' => $association,
            'schedule' => $schedule,
        ]);
    }

    public function generateMatchesStore(Association $association, Schedule $schedule, Request $request)
    {
        $request->validate([
            'generate' => 'required|in:clear,random',
        ]);

        if (! empty($this->scheduleGenerationErrors($schedule))) {
            return redirect()->route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]);
        }

        if ($request->generate === 'clear') {
            $this->clearMatchAssignments($schedule);

            $request->session()->flash('message', __('Successfully cleared match assignments'));

            return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);
        }

        $this->truncateRounds($schedule);
        $this->stashGeneratedCandidate($schedule, $this->generateAutomaticCandidate($schedule));

        return redirect()->route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]);
    }

    /**
     * Shows the best candidate found by automatic generation, held in the
     * session since generateMatchesStore() - nothing is persisted to
     * rounds/matches until the admin explicitly accepts it.
     */
    public function generateMatchesReview(Association $association, Schedule $schedule)
    {
        $candidateData = session($this->sessionKey($schedule, 'candidate'));
        $reportData = session($this->sessionKey($schedule, 'report'));

        if ($candidateData === null || $reportData === null) {
            return redirect()->route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]);
        }

        return view('schedule.generate-matches-review', [
            'association' => $association,
            'schedule' => $schedule,
            'candidate' => ScheduleCandidate::fromArray($candidateData),
            'report' => GenerationReport::fromArray($reportData),
            'teamNames' => $schedule->association->teams->pluck('name', 'id'),
        ]);
    }

    /**
     * Commits exactly the candidate the admin reviewed: re-reads it from the
     * session rather than re-running generation, so what gets persisted is
     * guaranteed to match what was shown on the review screen.
     */
    public function generateMatchesAccept(Association $association, Schedule $schedule, Request $request)
    {
        $candidateData = session($this->sessionKey($schedule, 'candidate'));

        if ($candidateData === null) {
            return redirect()->route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]);
        }

        $candidate = ScheduleCandidate::fromArray($candidateData);

        DB::transaction(function () use ($schedule, $candidate) {
            $this->truncateRounds($schedule);
            $this->persistCandidate($schedule, $candidate);
        });

        session()->forget([$this->sessionKey($schedule, 'candidate'), $this->sessionKey($schedule, 'report')]);

        $request->session()->flash('message', __('Successfully generated rounds'));

        return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);
    }

    public function generateMatchesRetry(Association $association, Schedule $schedule)
    {
        if (! empty($this->scheduleGenerationErrors($schedule))) {
            return redirect()->route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]);
        }

        $this->stashGeneratedCandidate($schedule, $this->generateAutomaticCandidate($schedule));

        return redirect()->route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]);
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
        $round_number = 1;

        foreach ((new RoundDatePlanner)->datesFor($start_date, $end_date, $weekday) as $date) {
            $round = new Round;

            $round->schedule_id = $schedule->id;
            $round->division_id = $schedule->division_id;
            $round->series_id = $schedule->series_id;

            $round->start_date = $date;
            $round->end_date = $date;
            $round->name = 'Round '.$round_number;

            $round->save();

            $round_number += 1;

            $round->createMatches();
        }
    }

    /**
     * Runs the constraint-aware generator (see App\Services\ScheduleGeneration)
     * against this schedule's active teams/venues and its own persisted
     * date range/weekday - the same inputs createRoundsManual reads.
     */
    private function generateAutomaticCandidate(Schedule $schedule): GenerationResult
    {
        $association = $schedule->association;

        $activeTeams = $association->activeTeams->map(fn ($team) => TeamInput::fromModel($team))->all();
        $activeVenues = $association->activeVenues->map(fn ($venue) => VenueInput::fromModel($venue))->all();
        $roundDates = (new RoundDatePlanner)->datesFor($schedule->start_date, $schedule->end_date, $schedule->weekday);

        return app(ScheduleGenerator::class)->generate($roundDates, $activeTeams, $activeVenues, GenerationConfig::fromConfig());
    }

    private function stashGeneratedCandidate(Schedule $schedule, GenerationResult $result): void
    {
        session([
            $this->sessionKey($schedule, 'candidate') => $result->candidate->toArray(),
            $this->sessionKey($schedule, 'report') => $result->report->toArray(),
        ]);
    }

    private function sessionKey(Schedule $schedule, string $suffix): string
    {
        return "schedule_generation.{$schedule->id}.{$suffix}";
    }

    /**
     * Persists exactly the rounds/matches described by a generated
     * candidate. Bypasses Round::createMatches() (which creates one empty
     * match per active venue) because the candidate already knows which
     * venues are used each round and who's playing.
     */
    private function persistCandidate(Schedule $schedule, ScheduleCandidate $candidate): void
    {
        $roundNumber = 1;

        foreach ($candidate->rounds as $roundCandidate) {
            $round = new Round;

            $round->schedule_id = $schedule->id;
            $round->division_id = $schedule->division_id;
            $round->series_id = $schedule->series_id;

            $round->start_date = $roundCandidate->date;
            $round->end_date = $roundCandidate->date;
            $round->name = 'Round '.$roundNumber;

            $round->save();

            $roundNumber += 1;

            foreach ($roundCandidate->matches as $matchCandidate) {
                $match = new PLMatch;

                $match->name = $matchCandidate->venueName.' – '.$roundCandidate->date->format('m-d-Y');
                $match->association_id = $schedule->association_id;
                $match->series_id = $schedule->series_id;
                $match->division_id = $schedule->division_id;
                $match->schedule_id = $schedule->id;
                $match->round_id = $round->id;
                $match->venue_id = $matchCandidate->venueId;
                $match->sequence = 1;
                $match->start_date = $roundCandidate->date;
                $match->end_date = $roundCandidate->date;
                $match->home_team_id = $matchCandidate->homeTeamId;
                $match->away_team_id = $matchCandidate->awayTeamId;

                $match->save();
            }
        }
    }

    /**
     * Clears Home/Away team assignments on this schedule's Matches without
     * touching the Round/Match rows themselves.
     */
    private function clearMatchAssignments(Schedule $schedule): void
    {
        PLMatch::where('schedule_id', $schedule->id)->update([
            'home_team_id' => null,
            'away_team_id' => null,
        ]);
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
