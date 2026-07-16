<?php

namespace App\Services\ScheduleGeneration;

/**
 * Randomized-restart search: builds full candidate schedules attempt by
 * attempt (never true exhaustive brute force - intractable in general and
 * unnecessary at league scale), scores each against the soft criteria, and
 * keeps the best hard-constraint-valid one seen within the attempt/time
 * budget. See plan.md for the constraint model this implements.
 */
final class ScheduleGenerator
{
    public function __construct(
        private readonly Rng $rng,
        private readonly ScheduleScorer $scorer,
    ) {
    }

    /**
     * @param \DateTimeImmutable[] $roundDates
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    public function generate(array $roundDates, array $activeTeams, array $activeVenues, GenerationConfig $config): GenerationResult
    {
        $startedAt = microtime(true);

        if (empty($roundDates)) {
            return $this->degenerateResult(
                new ScheduleCandidate([]),
                "No round dates were generated for this schedule's date range and weekday.",
                0,
                $this->elapsedMs($startedAt),
            );
        }

        if (count($activeTeams) < 2) {
            return $this->degenerateResult(
                $this->allByeCandidate($roundDates, $activeTeams),
                'Fewer than 2 active teams are available, so no matches can be scheduled.',
                0,
                $this->elapsedMs($startedAt),
            );
        }

        if (empty($activeVenues)) {
            return $this->degenerateResult(
                $this->allByeCandidate($roundDates, $activeTeams),
                'No active venues are available, so no matches can be scheduled.',
                0,
                $this->elapsedMs($startedAt),
            );
        }

        $attempts = 0;
        $best = null;
        $bestReport = null;

        // RoundRobinConstructor (the exclusive-home-venue seed) is set aside
        // for now - greedy-only below is the sole code path while Automatic
        // assignment is reworked to populate existing Matches instead of
        // creating/deleting Rounds. See RoundRobinConstructor's class
        // docblock; left in place as dead code rather than deleted in case
        // it's revisited later.
        //
        // $seed = (new RoundRobinConstructor())->construct($roundDates, $activeTeams, $activeVenues);
        //
        // if ($seed !== null) {
        //     $seedReport = $this->scorer->score($seed, $activeTeams, $activeVenues, $config);
        //
        //     if ($seedReport->hardConstraintsSatisfied) {
        //         $best = $seed;
        //         $bestReport = $seedReport;
        //
        //         if ($bestReport->score <= 0.0) {
        //             return new GenerationResult($best, $bestReport, $attempts, $this->elapsedMs($startedAt));
        //         }
        //     }
        // }

        while ($attempts < $config->maxAttempts && $this->elapsedMs($startedAt) < $config->timeBudgetMs) {
            $attempts++;

            $candidate = $this->attempt($roundDates, $activeTeams, $activeVenues);
            $report = $this->scorer->score($candidate, $activeTeams, $activeVenues, $config);

            if (! $report->hardConstraintsSatisfied) {
                continue;
            }

            if ($bestReport === null || $report->score < $bestReport->score) {
                $best = $candidate;
                $bestReport = $report;
            }

            if ($bestReport->score <= 0.0) {
                break;
            }
        }

        if ($best === null) {
            // Every attempt within budget failed to satisfy the hard
            // constraints (in practice RoundBuilder's own construction can't
            // violate any of the remaining ones, so this shouldn't happen -
            // but it keeps a concrete, honestly-flagged-degenerate schedule
            // available if it ever does). Run one more attempt purely so
            // there's something to show the admin.
            $fallback = $this->attempt($roundDates, $activeTeams, $activeVenues);
            $report = $this->scorer->score($fallback, $activeTeams, $activeVenues, $config);
            $reason = "Could not find a schedule that satisfies all required constraints within {$attempts} attempts.";

            return new GenerationResult(
                $fallback,
                new GenerationReport(
                    hardConstraintsSatisfied: false,
                    hardViolations: $report->hardViolations,
                    softViolationsByCriterion: $report->softViolationsByCriterion,
                    score: $report->score,
                    degenerate: true,
                    degenerateReason: $reason,
                    softCriteriaScores: $report->softCriteriaScores,
                ),
                $attempts,
                $this->elapsedMs($startedAt),
            );
        }

        return new GenerationResult($best, $bestReport, $attempts, $this->elapsedMs($startedAt));
    }

    /**
     * @param \DateTimeImmutable[] $roundDates
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    private function attempt(array $roundDates, array $activeTeams, array $activeVenues): ScheduleCandidate
    {
        $builder = new RoundBuilder($this->rng);

        $teamIds = array_map(fn (TeamInput $t) => $t->id, $activeTeams);
        $byeCountByTeam = array_fill_keys($teamIds, 0);
        $homeCountByTeam = array_fill_keys($teamIds, 0);
        $awayCountByTeam = array_fill_keys($teamIds, 0);
        $homeVenueAppearancesByTeam = array_fill_keys($teamIds, 0);
        $lastVenueByTeam = [];
        $lastMeetingRoundByPair = [];

        $rounds = [];

        foreach ($roundDates as $index => $date) {
            $rounds[] = $builder->build(
                $date,
                $activeTeams,
                $activeVenues,
                $byeCountByTeam,
                $lastVenueByTeam,
                $lastMeetingRoundByPair,
                $homeCountByTeam,
                $awayCountByTeam,
                $homeVenueAppearancesByTeam,
                $index,
            );
        }

        return new ScheduleCandidate($rounds);
    }

    /**
     * @param \DateTimeImmutable[] $roundDates
     * @param TeamInput[] $activeTeams
     */
    private function allByeCandidate(array $roundDates, array $activeTeams): ScheduleCandidate
    {
        $teamIds = array_map(fn (TeamInput $t) => $t->id, $activeTeams);

        $rounds = array_map(
            fn (\DateTimeImmutable $date) => new RoundCandidate($date, [], $teamIds),
            $roundDates,
        );

        return new ScheduleCandidate($rounds);
    }

    private function degenerateResult(ScheduleCandidate $candidate, string $reason, int $attempts, float $elapsedMs): GenerationResult
    {
        return new GenerationResult(
            $candidate,
            new GenerationReport(
                hardConstraintsSatisfied: true,
                hardViolations: [],
                softViolationsByCriterion: [],
                score: 0.0,
                degenerate: true,
                degenerateReason: $reason,
            ),
            $attempts,
            $elapsedMs,
        );
    }

    private function elapsedMs(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }
}
