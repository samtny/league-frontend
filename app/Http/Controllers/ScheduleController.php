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
use App\Services\ScheduleGeneration\GenerationStrategy;
use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundDatePlanner;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\ScheduleGenerator;
use App\Services\ScheduleGeneration\StrategyRecommendation;
use App\Services\ScheduleGeneration\StrategyRecommender;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use App\Venue;
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

        return redirect()->route('series.view', ['association' => $association, 'series' => $series]);
    }

    public function store(Association $association, Series $series, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'division_id' => 'required|exists:divisions,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'weekday' => 'nullable|in:sun,mon,tue,wed,thu,fri,sat',
            'venue_ids' => 'array',
            'venue_ids.*' => 'integer|exists:venues,id',
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
        $schedule->archived = $request->has('archived');

        $division = Division::where(['id' => $division_id])->first();

        $schedule->sequence = ! empty($division) ? $division->sequence : null;

        $schedule->save();

        $venueIds = $request->input('venue_ids', []);

        if (! empty($venueIds)) {
            Venue::whereIn('id', $venueIds)->update(['schedule_id' => $schedule->id]);
        }

        $schedule->venues_configured = true;
        $schedule->save();

        // Same as editing a schedule: if start/end/weekday are all present,
        // Rounds are generated to match right away - no separate "Generate
        // Schedule" step during creation.
        $this->regenerateRounds($schedule);

        return redirect()->route('series.view', ['association' => $association, 'series' => $series]);
    }

    public function update(Association $association, Schedule $schedule, Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'venue_ids' => 'array',
            'venue_ids.*' => 'integer|exists:venues,id',
        ]);

        $pendingValues = [
            'name' => $request->name,
            'division_id' => $request->division_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'weekday' => $request->weekday,
            'archived' => $request->archived,
            'venue_ids' => $request->input('venue_ids', []),
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
        $schedule->venues_configured = true;

        $schedule->save();

        $venueIds = $values['venue_ids'] ?? [];

        Venue::where('schedule_id', $schedule->id)->whereNotIn('id', $venueIds)->update(['schedule_id' => null]);

        if (! empty($venueIds)) {
            Venue::whereIn('id', $venueIds)->update(['schedule_id' => $schedule->id]);
        }
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
     * missing a field generation depends on (start/end date, weekday,
     * division), that's caught here before either option is even offered -
     * generateAutomaticCandidate() fails silently (e.g. strtolower(null)
     * never matches a weekday) rather than throwing. If any of this
     * schedule's Matches already have a Home/Away team assigned, a warning
     * gate is shown first: both Clear and Automatic (which runs its own
     * implicit Clear on accept - see generateMatchesAccept()) end up
     * clearing those assignments, so this is purely informational -
     * "Proceed" just moves on to generateMatchesSelect() below, nothing is
     * mutated here.
     */
    public function generateMatches(Association $association, Schedule $schedule)
    {
        $missingFields = $this->generateMatchesErrors($schedule);

        if (! empty($missingFields)) {
            return view('schedule.generate-matches-invalid', [
                'association' => $association,
                'schedule' => $schedule,
                'missingFields' => $missingFields,
            ]);
        }

        if ($this->scheduleHasAssignedMatches($schedule)) {
            return view('schedule.generate-matches-confirm', [
                'association' => $association,
                'schedule' => $schedule,
            ]);
        }

        return view('schedule.generate-matches-select', [
            'association' => $association,
            'schedule' => $schedule,
            'strategies' => GenerationStrategy::cases(),
            'recommendation' => $this->recommendedStrategy($schedule),
        ]);
    }

    /**
     * The actual Clear/Automatic select screen - reached either directly
     * from generateMatches() above (no assigned matches to warn about) or
     * via its confirm gate's "Proceed" link.
     */
    public function generateMatchesSelect(Association $association, Schedule $schedule)
    {
        $missingFields = $this->generateMatchesErrors($schedule);

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
            'strategies' => GenerationStrategy::cases(),
            'recommendation' => $this->recommendedStrategy($schedule),
        ]);
    }

    public function generateMatchesStore(Association $association, Schedule $schedule, Request $request)
    {
        $request->validate([
            'generate' => 'required|in:clear,random',
            // Absent/null defaults to the engine's own recommendation (see
            // resolveStrategy()) so existing links/tests that don't know
            // about strategies at all keep working unchanged.
            'strategy' => 'nullable|in:'.implode(',', GenerationStrategy::values()),
        ]);

        if (! empty($this->generateMatchesErrors($schedule))) {
            return redirect()->route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]);
        }

        if ($request->generate === 'clear') {
            $this->clearMatchAssignments($schedule);

            $request->session()->flash('message', __('Successfully cleared match assignments'));

            return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);
        }

        $strategy = $this->resolveStrategy($schedule, $request->input('strategy'));

        // Stashed independently of the candidate/report so
        // generateMatchesRetry() can reuse the SAME chosen strategy without
        // silently reverting to the recommendation - see that method.
        session([$this->sessionKey($schedule, 'strategy') => $strategy->value]);

        $this->stashGeneratedCandidate($schedule, $this->generateAutomaticCandidate($schedule, $strategy));

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
     * guaranteed to match what was shown on the review screen. Runs an
     * implicit Clear first (same as the "Clear" option one step up) so
     * Automatic always starts from a clean slate - see
     * applyCandidateToExistingMatches() for why that matters.
     */
    public function generateMatchesAccept(Association $association, Schedule $schedule, Request $request)
    {
        $candidateData = session($this->sessionKey($schedule, 'candidate'));

        if ($candidateData === null) {
            return redirect()->route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]);
        }

        $candidate = ScheduleCandidate::fromArray($candidateData);

        DB::transaction(function () use ($schedule, $candidate) {
            $this->clearMatchAssignments($schedule);
            $this->applyCandidateToExistingMatches($schedule, $candidate);
        });

        session()->forget([
            $this->sessionKey($schedule, 'candidate'),
            $this->sessionKey($schedule, 'report'),
            $this->sessionKey($schedule, 'strategy'),
        ]);

        $request->session()->flash('message', __('Successfully generated rounds'));

        return redirect()->route('schedule.view', ['association' => $association, 'schedule' => $schedule]);
    }

    /**
     * Regenerates using the SAME strategy generateMatchesStore() stashed,
     * not the engine's recommendation - otherwise an admin who deliberately
     * picked a non-default strategy would see it silently revert on every
     * "Discard & Regenerate" click.
     */
    public function generateMatchesRetry(Association $association, Schedule $schedule)
    {
        if (! empty($this->generateMatchesErrors($schedule))) {
            return redirect()->route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]);
        }

        $strategy = $this->resolveStrategy($schedule, session($this->sessionKey($schedule, 'strategy')));

        $this->stashGeneratedCandidate($schedule, $this->generateAutomaticCandidate($schedule, $strategy));

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

    /**
     * Gate for the Generate Matches wizard specifically: everything
     * scheduleGenerationErrors() requires, plus a Division - the automatic
     * generator filters Teams/Venues by the Schedule's Division (see
     * generateAutomaticCandidate()), so it can't run without one. Kept
     * separate from scheduleGenerationErrors() itself since that method also
     * gates regenerateRounds() (Round/Match slot creation), which still
     * supports a divisionless Schedule via Round::createMatches()'s own
     * null-matches-null fallback.
     */
    private function generateMatchesErrors(Schedule $schedule): array
    {
        $missing = $this->scheduleGenerationErrors($schedule);

        if (empty($schedule->division_id)) {
            $missing[] = 'Division';
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
     * with the given strategy (see GenerationStrategy/StrategyRecommender)
     * against this schedule's active teams/venues and its own already-
     * persisted Rounds/Matches - see scheduleGenerationInputs() for how
     * those are built.
     */
    private function generateAutomaticCandidate(Schedule $schedule, GenerationStrategy $strategy): GenerationResult
    {
        $inputs = $this->scheduleGenerationInputs($schedule);

        return app(ScheduleGenerator::class)->generate(
            $inputs['rounds'],
            $inputs['activeTeams'],
            $inputs['activeVenues'],
            GenerationConfig::forAssociation($schedule->association),
            $strategy,
        );
    }

    /**
     * The engine's default strategy pick for this Schedule's current
     * league shape (plan.md §5) - shown on the select screen as the
     * pre-checked radio and used by resolveStrategy() whenever no explicit
     * choice is available. This is a DEFAULT only: every GenerationStrategy
     * stays selectable regardless (decision 2.7).
     */
    private function recommendedStrategy(Schedule $schedule): StrategyRecommendation
    {
        $inputs = $this->scheduleGenerationInputs($schedule);

        return app(StrategyRecommender::class)->recommend(
            $inputs['activeTeams'],
            $inputs['activeVenues'],
            count($inputs['rounds']),
            GenerationConfig::forAssociation($schedule->association),
        );
    }

    /**
     * Resolves a raw strategy value (the posted 'strategy' field, or
     * whatever generateMatchesStore() previously stashed in the session)
     * into a real GenerationStrategy - anything missing, not a string, or
     * not a recognized value falls back to the engine's own recommendation
     * for this Schedule, rather than erroring. generateMatchesStore()'s own
     * validation already rejects an unrecognized posted value before this
     * is ever reached from there, so the fallback mainly protects
     * generateMatchesRetry() against a missing/stale session key.
     */
    private function resolveStrategy(Schedule $schedule, mixed $strategyValue): GenerationStrategy
    {
        if (is_string($strategyValue)) {
            $strategy = GenerationStrategy::tryFrom($strategyValue);

            if ($strategy !== null) {
                return $strategy;
            }
        }

        return $this->recommendedStrategy($schedule)->strategy;
    }

    /**
     * Builds the RoundInput/TeamInput/VenueInput triple every
     * ScheduleGenerator::generate() call and StrategyRecommender::recommend()
     * call needs, from this schedule's active teams/venues and its own
     * already-persisted Rounds/Matches. Each Round becomes a RoundInput
     * carrying the real matches.id of every empty match slot it already has
     * (created by Round::createMatches() one per active venue) - the
     * generator never invents Round/Match rows, it only ever assigns teams
     * into slots that already exist. Slots pointing at a since-deactivated
     * venue are excluded from the pool the same way they always were
     * (RoundBuilder only ever drew from the active venue pool). A Round
     * flagged off_week or playoffs_week is excluded from this list entirely
     * - not merely given empty slots, but genuinely absent, exactly as if it
     * didn't exist for solving purposes (round-index-based soft criteria
     * like FullCycleSpacingCriterion naturally treat the remaining rounds as
     * contiguous, with no gap for the skipped week). Note this only
     * controls what the SOLVER (and the recommendation) considers; the
     * Accept flow still clears and (for included rounds) reassigns matches
     * schedule-wide regardless of this filter - see
     * generateMatchesAccept()/clearMatchAssignments().
     *
     * @return array{rounds: RoundInput[], activeTeams: TeamInput[], activeVenues: VenueInput[]}
     */
    private function scheduleGenerationInputs(Schedule $schedule): array
    {
        $association = $schedule->association;

        // Both filtered to this Schedule's Division - generateMatchesErrors()
        // guarantees division_id is set before this runs, so eligibility
        // here is a plain equality/membership check, no null-matches-null
        // fallback needed (contrast Round::createMatches(), which still
        // handles a null division for legacy Schedules).
        $eligibleTeams = $association->activeTeams->filter(fn ($team) => $team->division_id == $schedule->division_id);
        $eligibleVenues = $association->activeVenues->filter(fn ($venue) => $venue->divisions->contains('id', $schedule->division_id));

        // values() before all(): Collection::filter() above PRESERVES the
        // original keys, so without this the generator receives arrays keyed
        // e.g. [0, 1, 3, 5] whenever any team/venue was filtered out. Several
        // consumers index these positionally as 0..count-1 (slot assignment,
        // pairwise iteration), which then reads a missing key. This caused a
        // real 500 on the generate-matches screen; unit fixtures never caught
        // it because they hand-build zero-indexed lists.
        $activeTeams = $eligibleTeams->map(fn ($team) => TeamInput::fromModel($team))->values()->all();
        $activeVenues = $eligibleVenues->map(fn ($venue) => VenueInput::fromModel($venue))->values()->all();
        $activeVenueIds = $eligibleVenues->pluck('id')->flip();

        $rounds = $schedule->rounds()->with('matches.venue')->orderBy('start_date')->get()
            ->reject(fn (Round $round) => $round->off_week || $round->playoffs_week)
            ->values() // reject() preserves original keys; the generator (e.g. RoundRobinConstructor) indexes this array 0..count-1 and needs it re-keyed contiguously after any rounds are excluded
            ->map(function (Round $round) use ($activeVenueIds) {
                $slots = $round->matches
                    ->filter(fn (PLMatch $match) => $match->venue_id !== null && $activeVenueIds->has($match->venue_id))
                    ->map(fn (PLMatch $match) => new MatchSlotInput($match->id, $match->venue_id, optional($match->venue)->name ?? ''))
                    ->values()
                    ->all();

                return new RoundInput($round->id, $round->start_date->toDateTimeImmutable(), $slots);
            })
            ->all();

        return ['rounds' => $rounds, 'activeTeams' => $activeTeams, 'activeVenues' => $activeVenues];
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
     * Applies a generated candidate's Home/Away assignments onto this
     * schedule's own existing PLMatch rows, keyed directly by the real
     * matches.id each MatchCandidate carries (see generateAutomaticCandidate()) -
     * Rounds/Matches are never created or deleted here. This only ever
     * writes new team ids - it never nulls out ones a candidate doesn't
     * happen to touch - so the caller (generateMatchesAccept()) always runs
     * clearMatchAssignments() immediately first; otherwise a Match slot the
     * new candidate skips (e.g. capacity < venue count that round) could
     * keep a stale assignment from a previous run.
     */
    private function applyCandidateToExistingMatches(Schedule $schedule, ScheduleCandidate $candidate): void
    {
        foreach ($candidate->rounds as $roundCandidate) {
            foreach ($roundCandidate->matches as $matchCandidate) {
                if ($matchCandidate->matchId === null) {
                    continue;
                }

                PLMatch::where('id', $matchCandidate->matchId)
                    ->where('schedule_id', $schedule->id)
                    ->update([
                        'home_team_id' => $matchCandidate->homeTeamId,
                        'away_team_id' => $matchCandidate->awayTeamId,
                    ]);
            }
        }
    }

    /**
     * True if any of this schedule's Matches already have a Home or Away
     * team assigned - gates the Generate Matches wizard's confirm warning.
     */
    private function scheduleHasAssignedMatches(Schedule $schedule): bool
    {
        return PLMatch::where('schedule_id', $schedule->id)
            ->where(function ($query) {
                $query->whereNotNull('home_team_id')->orWhereNotNull('away_team_id');
            })
            ->exists();
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
