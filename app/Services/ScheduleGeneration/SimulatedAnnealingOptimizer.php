<?php

namespace App\Services\ScheduleGeneration;

/**
 * The polish phase: local search over an already hard-valid ScheduleCandidate
 * via simulated annealing with reheat-on-new-best, replacing plain
 * randomized restart. Standard Metropolis acceptance (always accept an
 * improving move; accept a worsening move with probability exp(-delta/T));
 * temperature resets to its initial value whenever a new best-ever candidate
 * is found (rather than cooling on a fixed schedule), and cools geometrically
 * otherwise. Every candidate a move produces is rescored in full via the
 * unmodified ScheduleScorer and rejected outright if it fails a hard
 * constraint - the search never enters a hard-invalid state, and the
 * best-ever candidate returned can only ever be as good as or better than
 * the seed it started from.
 */
final class SimulatedAnnealingOptimizer
{
    private const COOLING_FACTOR = 0.95;

    private const PROBE_MOVES = 200;

    private const ACCEPTANCE_QUANTILE = 0.8;

    public function __construct(
        private readonly Rng $rng,
        private readonly ScheduleScorer $scorer,
    ) {
    }

    /**
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     * @param array<string, float> $fixedTierThresholds criterion key => max allowed raw penalty; any candidate
     *   exceeding a threshold is rejected the same way a hard-constraint violation is
     * @return array{candidate: ScheduleCandidate, report: GenerationReport, iterations: int}
     */
    public function optimize(
        ScheduleCandidate $initial,
        GenerationReport $initialReport,
        array $rounds,
        array $activeTeams,
        array $activeVenues,
        GenerationConfig $config,
        float $startedAt,
        array $fixedTierThresholds = [],
    ): array {
        $best = $initial;
        $bestReport = $initialReport;

        if (empty($rounds) || $bestReport->score <= 0.0) {
            return ['candidate' => $best, 'report' => $bestReport, 'iterations' => 0];
        }

        $current = $initial;
        $currentReport = $initialReport;

        $t0 = $this->initialTemperature($current, $currentReport, $rounds, $activeTeams, $activeVenues, $config, $fixedTierThresholds);
        $temperature = $t0;
        $epochSize = max(1, SlotCount::total($rounds));
        $iterations = 0;

        while ($iterations < $config->maxAttempts && $this->elapsedMs($startedAt) < $config->timeBudgetMs) {
            $iterations++;

            $candidate = $this->applyRandomMove($current, $rounds, $activeTeams) ?? $current;
            $report = $this->scorer->score($candidate, $activeTeams, $activeVenues, $config);

            if (! $report->hardConstraintsSatisfied || ! $this->satisfiesFixedTiers($report, $fixedTierThresholds)) {
                continue;
            }

            $delta = $report->score - $currentReport->score;
            $accept = $delta <= 0.0 || $this->rng->nextFloat() < exp(-$delta / max($temperature, 1e-9));

            if ($accept) {
                $current = $candidate;
                $currentReport = $report;

                if ($currentReport->score < $bestReport->score) {
                    $best = $current;
                    $bestReport = $currentReport;
                    $temperature = $t0;

                    if ($bestReport->score <= 0.0) {
                        break;
                    }
                }
            }

            if ($iterations % $epochSize === 0) {
                $temperature = max($temperature * self::COOLING_FACTOR, $t0 * 1e-4);
            }
        }

        return ['candidate' => $best, 'report' => $bestReport, 'iterations' => $iterations];
    }

    /**
     * Samples PROBE_MOVES random moves from the starting candidate and sets
     * T0 so the mean worsening move is accepted with probability
     * ACCEPTANCE_QUANTILE at the start of the search. Because the dominance-
     * weighted objective (see GenerationConfig::tierWeight()) separates
     * priority tiers by orders of magnitude, this makes the search naturally
     * near-lexicographic for free: a worsening move touching a higher-
     * priority tier has a delta orders of magnitude larger than T0, so
     * exp(-delta/T0) is effectively zero and it's almost never accepted,
     * while moves affecting only lower tiers anneal normally.
     *
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     * @param array<string, float> $fixedTierThresholds see optimize() - the probe must use the same gate as the
     *   main loop, or T0 gets calibrated against worsening deltas the main loop would never actually accept
     */
    private function initialTemperature(
        ScheduleCandidate $candidate,
        GenerationReport $report,
        array $rounds,
        array $activeTeams,
        array $activeVenues,
        GenerationConfig $config,
        array $fixedTierThresholds = [],
    ): float {
        $positiveDeltas = [];
        $probe = $candidate;
        $probeReport = $report;

        for ($i = 0; $i < self::PROBE_MOVES; $i++) {
            $next = $this->applyRandomMove($probe, $rounds, $activeTeams) ?? $probe;
            $nextReport = $this->scorer->score($next, $activeTeams, $activeVenues, $config);

            if (! $nextReport->hardConstraintsSatisfied || ! $this->satisfiesFixedTiers($nextReport, $fixedTierThresholds)) {
                continue;
            }

            $delta = $nextReport->score - $probeReport->score;

            if ($delta > 0.0) {
                $positiveDeltas[] = $delta;
            }

            // Keep exploring from wherever the probe lands, so the sample
            // covers a variety of states rather than repeatedly mutating the
            // same starting point.
            $probe = $next;
            $probeReport = $nextReport;
        }

        if (empty($positiveDeltas)) {
            // No worsening move was observed in the probe budget (e.g. an
            // already-perfect or tiny input) - a small positive temperature
            // is harmless since there's nothing left to anneal away.
            return 1.0;
        }

        $mean = array_sum($positiveDeltas) / count($positiveDeltas);

        return max(1e-6, -$mean / log(self::ACCEPTANCE_QUANTILE));
    }

    /**
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     */
    private function applyRandomMove(ScheduleCandidate $candidate, array $rounds, array $activeTeams): ?ScheduleCandidate
    {
        $moves = [
            fn () => $this->homeAwayFlip($candidate),
            fn () => $this->venueSwap($candidate),
            fn () => $this->opponentRecombine($candidate),
            fn () => $this->byeSwap($candidate),
            fn () => $this->roundRebuild($candidate, $rounds, $activeTeams),
        ];

        $move = $moves[$this->rng->nextInt(0, count($moves) - 1)];

        return $move();
    }

    /**
     * Swap home/away on a single match. Structurally hard-safe except for
     * H4 (away-at-own-venue), which the caller's post-hoc scorer check
     * catches like every other move.
     */
    private function homeAwayFlip(ScheduleCandidate $candidate): ?ScheduleCandidate
    {
        $roundIndex = $this->pickRoundIndexWhere($candidate, fn (RoundCandidate $r) => count($r->matches) >= 1);

        if ($roundIndex === null) {
            return null;
        }

        $round = $candidate->rounds[$roundIndex];
        $matchIndex = $this->rng->nextInt(0, count($round->matches) - 1);
        $match = $round->matches[$matchIndex];

        $newMatches = $round->matches;
        $newMatches[$matchIndex] = new MatchCandidate($match->venueId, $match->venueName, $match->awayTeamId, $match->homeTeamId, $match->matchId);

        return $this->replaceRound($candidate, $roundIndex, new RoundCandidate($round->date, $newMatches, $round->byeTeamIds, $round->roundId));
    }

    /**
     * Swap the venue slot between two matches in the same round, keeping
     * each match's own home/away teams. Structurally double-book/bye-safe;
     * can only fail H4, caught by the post-hoc scorer check.
     */
    private function venueSwap(ScheduleCandidate $candidate): ?ScheduleCandidate
    {
        $roundIndex = $this->pickRoundIndexWhere($candidate, fn (RoundCandidate $r) => count($r->matches) >= 2);

        if ($roundIndex === null) {
            return null;
        }

        $round = $candidate->rounds[$roundIndex];
        [$i, $j] = $this->pickTwoDistinctIndices(count($round->matches));
        $matchA = $round->matches[$i];
        $matchB = $round->matches[$j];

        $newMatches = $round->matches;
        $newMatches[$i] = new MatchCandidate($matchB->venueId, $matchB->venueName, $matchA->homeTeamId, $matchA->awayTeamId, $matchB->matchId);
        $newMatches[$j] = new MatchCandidate($matchA->venueId, $matchA->venueName, $matchB->homeTeamId, $matchB->awayTeamId, $matchA->matchId);

        return $this->replaceRound($candidate, $roundIndex, new RoundCandidate($round->date, $newMatches, $round->byeTeamIds, $round->roundId));
    }

    /**
     * Re-pair two matches within the same round - (A,B),(C,D) becomes
     * (A,C),(B,D) or (A,D),(B,C) at random, keeping each match's own venue
     * slot. Structurally double-book/bye-safe (the 4 teams involved are
     * already known distinct, since each plays at most one match per
     * round); can only fail H4, caught by the post-hoc scorer check.
     */
    private function opponentRecombine(ScheduleCandidate $candidate): ?ScheduleCandidate
    {
        $roundIndex = $this->pickRoundIndexWhere($candidate, fn (RoundCandidate $r) => count($r->matches) >= 2);

        if ($roundIndex === null) {
            return null;
        }

        $round = $candidate->rounds[$roundIndex];
        [$i, $j] = $this->pickTwoDistinctIndices(count($round->matches));
        $matchA = $round->matches[$i];
        $matchB = $round->matches[$j];

        $newMatches = $round->matches;

        if ($this->rng->nextInt(0, 1) === 1) {
            // Swap home teams: each venue now hosts the other match's home team.
            $newMatches[$i] = new MatchCandidate($matchA->venueId, $matchA->venueName, $matchB->homeTeamId, $matchA->awayTeamId, $matchA->matchId);
            $newMatches[$j] = new MatchCandidate($matchB->venueId, $matchB->venueName, $matchA->homeTeamId, $matchB->awayTeamId, $matchB->matchId);
        } else {
            // Swap away teams: each venue keeps its own host, opponents change.
            $newMatches[$i] = new MatchCandidate($matchA->venueId, $matchA->venueName, $matchA->homeTeamId, $matchB->awayTeamId, $matchA->matchId);
            $newMatches[$j] = new MatchCandidate($matchB->venueId, $matchB->venueName, $matchB->homeTeamId, $matchA->awayTeamId, $matchB->matchId);
        }

        return $this->replaceRound($candidate, $roundIndex, new RoundCandidate($round->date, $newMatches, $round->byeTeamIds, $round->roundId));
    }

    /**
     * Swap a byed team into a match slot, sending the team it displaces to
     * the bye instead. Structurally double-book/bye-safe (the incoming team
     * wasn't playing this round); can only fail H4, caught by the post-hoc
     * scorer check.
     */
    private function byeSwap(ScheduleCandidate $candidate): ?ScheduleCandidate
    {
        $roundIndex = $this->pickRoundIndexWhere(
            $candidate,
            fn (RoundCandidate $r) => count($r->matches) >= 1 && count($r->byeTeamIds) >= 1,
        );

        if ($roundIndex === null) {
            return null;
        }

        $round = $candidate->rounds[$roundIndex];
        $byeIndex = $this->rng->nextInt(0, count($round->byeTeamIds) - 1);
        $byeTeamId = $round->byeTeamIds[$byeIndex];

        $matchIndex = $this->rng->nextInt(0, count($round->matches) - 1);
        $match = $round->matches[$matchIndex];

        if ($this->rng->nextInt(0, 1) === 1) {
            $outgoingTeamId = $match->homeTeamId;
            $newMatch = new MatchCandidate($match->venueId, $match->venueName, $byeTeamId, $match->awayTeamId, $match->matchId);
        } else {
            $outgoingTeamId = $match->awayTeamId;
            $newMatch = new MatchCandidate($match->venueId, $match->venueName, $match->homeTeamId, $byeTeamId, $match->matchId);
        }

        $newMatches = $round->matches;
        $newMatches[$matchIndex] = $newMatch;

        $newByeTeamIds = $round->byeTeamIds;
        $newByeTeamIds[$byeIndex] = $outgoingTeamId;

        return $this->replaceRound($candidate, $roundIndex, new RoundCandidate($round->date, $newMatches, $newByeTeamIds, $round->roundId));
    }

    /**
     * Ruin-and-recreate: discard one round's assignments entirely and
     * rebuild it via RoundBuilder, seeded from the state the candidate's
     * own earlier rounds actually carry (not the state the seed had before
     * any moves were applied). Structurally hard-safe - RoundBuilder's
     * construction can't violate a hard constraint (see InitialSolutionBuilder)
     * - and is the large-neighborhood move that lets the search escape local
     * optima the small moves above can't reach.
     *
     * @param RoundInput[] $rounds
     * @param TeamInput[] $activeTeams
     */
    private function roundRebuild(ScheduleCandidate $candidate, array $rounds, array $activeTeams): ?ScheduleCandidate
    {
        $roundCount = count($candidate->rounds);

        if ($roundCount === 0) {
            return null;
        }

        $roundIndex = $this->rng->nextInt(0, $roundCount - 1);
        $state = $this->stateBeforeRound($candidate, $activeTeams, $roundIndex);

        $newRound = (new RoundBuilder($this->rng))->build(
            $rounds[$roundIndex],
            $activeTeams,
            $state['byeCountByTeam'],
            $state['lastVenueByTeam'],
            $state['lastMeetingRoundByPair'],
            $state['homeCountByTeam'],
            $state['awayCountByTeam'],
            $state['homeVenueAppearancesByTeam'],
            $roundIndex,
        );

        return $this->replaceRound($candidate, $roundIndex, $newRound);
    }

    /**
     * Replays the candidate's own rounds 0..roundIndex-1 to reconstruct the
     * running state RoundBuilder::build() expects, so a rebuilt round stays
     * consistent with whatever earlier rounds the search has already
     * mutated - not the original seed's state.
     *
     * @param TeamInput[] $activeTeams
     * @return array{byeCountByTeam: array<int,int>, lastVenueByTeam: array<int,int|null>, lastMeetingRoundByPair: array<string,int>, homeCountByTeam: array<int,int>, awayCountByTeam: array<int,int>, homeVenueAppearancesByTeam: array<int,int>}
     */
    private function stateBeforeRound(ScheduleCandidate $candidate, array $activeTeams, int $roundIndex): array
    {
        $teamIds = array_map(fn (TeamInput $t) => $t->id, $activeTeams);
        $homeVenueIdByTeam = [];

        foreach ($activeTeams as $team) {
            $homeVenueIdByTeam[$team->id] = $team->homeVenueId;
        }

        $byeCountByTeam = array_fill_keys($teamIds, 0);
        $homeCountByTeam = array_fill_keys($teamIds, 0);
        $awayCountByTeam = array_fill_keys($teamIds, 0);
        $homeVenueAppearancesByTeam = array_fill_keys($teamIds, 0);
        $lastVenueByTeam = [];
        $lastMeetingRoundByPair = [];

        for ($i = 0; $i < $roundIndex; $i++) {
            $round = $candidate->rounds[$i];

            foreach ($round->matches as $match) {
                $homeCountByTeam[$match->homeTeamId] = ($homeCountByTeam[$match->homeTeamId] ?? 0) + 1;
                $awayCountByTeam[$match->awayTeamId] = ($awayCountByTeam[$match->awayTeamId] ?? 0) + 1;
                $lastVenueByTeam[$match->homeTeamId] = $match->venueId;
                $lastVenueByTeam[$match->awayTeamId] = $match->venueId;
                $lastMeetingRoundByPair[PairKey::for($match->homeTeamId, $match->awayTeamId)] = $i;

                if (($homeVenueIdByTeam[$match->homeTeamId] ?? null) === $match->venueId) {
                    $homeVenueAppearancesByTeam[$match->homeTeamId] = ($homeVenueAppearancesByTeam[$match->homeTeamId] ?? 0) + 1;
                }
            }

            foreach ($round->byeTeamIds as $teamId) {
                $byeCountByTeam[$teamId] = ($byeCountByTeam[$teamId] ?? 0) + 1;
                $lastVenueByTeam[$teamId] = null;
            }
        }

        return [
            'byeCountByTeam' => $byeCountByTeam,
            'lastVenueByTeam' => $lastVenueByTeam,
            'lastMeetingRoundByPair' => $lastMeetingRoundByPair,
            'homeCountByTeam' => $homeCountByTeam,
            'awayCountByTeam' => $awayCountByTeam,
            'homeVenueAppearancesByTeam' => $homeVenueAppearancesByTeam,
        ];
    }

    private function pickTwoDistinctIndices(int $n): array
    {
        $i = $this->rng->nextInt(0, $n - 1);
        $j = $this->rng->nextInt(0, $n - 2);

        if ($j >= $i) {
            $j++;
        }

        return [$i, $j];
    }

    private function pickRoundIndexWhere(ScheduleCandidate $candidate, callable $predicate): ?int
    {
        $eligible = [];

        foreach ($candidate->rounds as $index => $round) {
            if ($predicate($round)) {
                $eligible[] = $index;
            }
        }

        if (empty($eligible)) {
            return null;
        }

        return $eligible[$this->rng->nextInt(0, count($eligible) - 1)];
    }

    private function replaceRound(ScheduleCandidate $candidate, int $roundIndex, RoundCandidate $newRound): ScheduleCandidate
    {
        $rounds = $candidate->rounds;
        $rounds[$roundIndex] = $newRound;

        return new ScheduleCandidate($rounds);
    }

    private function elapsedMs(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }

    /**
     * @param array<string, float> $fixedTierThresholds
     */
    private function satisfiesFixedTiers(GenerationReport $report, array $fixedTierThresholds): bool
    {
        foreach ($fixedTierThresholds as $key => $maxRaw) {
            foreach ($report->softCriteriaScores as $entry) {
                if ($entry['key'] === $key && $entry['raw'] > $maxRaw) {
                    return false;
                }
            }
        }

        return true;
    }
}
