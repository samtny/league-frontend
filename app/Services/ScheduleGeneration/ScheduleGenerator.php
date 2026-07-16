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

        // For the exclusive-home-venue case (every active team owns a
        // distinct active venue), a deterministic classical round-robin
        // construction can seed the search with a schedule already close to
        // (often exactly at) the theoretical minimum-breaks bound - the
        // greedy per-round loop below has no visibility into that
        // whole-schedule pattern and plateaus short of it (see plan.md).
        // Seed + polish: score it like any other candidate and only keep it
        // if it's hard-valid, so the loop below can only ever do as well or
        // better, never worse than today's greedy-only behavior.
        $seed = (new RoundRobinConstructor())->construct($roundDates, $activeTeams, $activeVenues);

        if ($seed !== null) {
            $seedReport = $this->scorer->score($seed, $activeTeams, $activeVenues, $config);

            if ($seedReport->hardConstraintsSatisfied) {
                $best = $seed;
                $bestReport = $seedReport;

                if ($bestReport->score <= 0.0) {
                    return new GenerationResult($best, $bestReport, $attempts, $this->elapsedMs($startedAt));
                }
            }
        }

        while ($attempts < $config->maxAttempts && $this->elapsedMs($startedAt) < $config->timeBudgetMs) {
            $attempts++;

            try {
                $candidate = $this->attempt($roundDates, $activeTeams, $activeVenues, true);
            } catch (UnableToBuildRoundException) {
                continue;
            }

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
            // Every strict attempt either failed to complete or never satisfied
            // the hard constraints within budget. Run one relaxed attempt
            // (opponent-repeat rejection off) purely so there's a concrete
            // schedule to show the admin, honestly flagged as degenerate.
            $fallback = $this->attempt($roundDates, $activeTeams, $activeVenues, false);
            $report = $this->scorer->score($fallback, $activeTeams, $activeVenues, $config);
            $reason = "Could not find a schedule that avoids repeat opponents in back-to-back rounds within {$attempts} attempts. "
                .'This usually means there are too few active teams for the number of rounds.';

            return new GenerationResult(
                $fallback,
                new GenerationReport(
                    hardConstraintsSatisfied: false,
                    hardViolations: $report->hardViolations,
                    softViolationsByCriterion: $report->softViolationsByCriterion,
                    score: $report->score,
                    degenerate: true,
                    degenerateReason: $reason,
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
    private function attempt(array $roundDates, array $activeTeams, array $activeVenues, bool $enforceNoConsecutiveOpponent): ScheduleCandidate
    {
        $builder = new RoundBuilder($this->rng);

        $teamIds = array_map(fn (TeamInput $t) => $t->id, $activeTeams);
        $byeCountByTeam = array_fill_keys($teamIds, 0);
        $homeCountByTeam = array_fill_keys($teamIds, 0);
        $awayCountByTeam = array_fill_keys($teamIds, 0);
        $homeVenueAppearancesByTeam = array_fill_keys($teamIds, 0);
        $lastOpponentByTeam = [];
        $lastVenueByTeam = [];
        $lastMeetingRoundByPair = [];

        $rounds = [];

        foreach ($roundDates as $index => $date) {
            $rounds[] = $builder->build(
                $date,
                $activeTeams,
                $activeVenues,
                $byeCountByTeam,
                $lastOpponentByTeam,
                $lastVenueByTeam,
                $lastMeetingRoundByPair,
                $homeCountByTeam,
                $awayCountByTeam,
                $homeVenueAppearancesByTeam,
                $index,
                $enforceNoConsecutiveOpponent,
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
