<?php

namespace App\Services\ScheduleGeneration;

/**
 * The construction phase: builds exactly one hard-valid ScheduleCandidate to
 * seed the polish phase that runs afterward. Uses RoundRobinConstructor's
 * break-minimal circle-method construction whenever eligible (every active
 * team owns a distinct active home venue, with at most one venue shared by
 * exactly two teams - see RoundRobinConstructor::isEligible()); otherwise
 * falls back to a single greedy pass through RoundBuilder. Either path is
 * guaranteed hard-valid by construction (see RoundBuilder/HardConstraints),
 * so no restart-for-feasibility is needed here - only the polish phase's
 * restart-for-quality.
 */
final class InitialSolutionBuilder
{
    public function __construct(
        private readonly Rng $rng,
    ) {}

    /**
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     * @param  VenueInput[]  $activeVenues
     * @param  bool  $palindromeSeam  threaded straight through to RoundRobinConstructor::construct() - see
     *                                its docblock. Default false ("mirrored") reproduces this method's
     *                                pre-existing behaviour exactly.
     */
    public function build(array $rounds, array $activeTeams, array $activeVenues, bool $palindromeSeam = false): ScheduleCandidate
    {
        $seed = (new RoundRobinConstructor($this->rng))->construct($rounds, $activeTeams, $activeVenues, $palindromeSeam);

        return $seed ?? $this->greedyPass($rounds, $activeTeams);
    }

    /**
     * A single randomized greedy pass (no restart) - also used directly by
     * ScheduleGenerator's polish loop, which wants a fresh randomized
     * attempt each iteration rather than repeating RoundRobinConstructor's
     * seed pointlessly (its break-minimal structure doesn't improve by
     * re-rolling, only who gets which slot changes).
     *
     * @param  RoundInput[]  $rounds
     * @param  TeamInput[]  $activeTeams
     */
    public function greedyPass(array $rounds, array $activeTeams): ScheduleCandidate
    {
        $builder = new RoundBuilder($this->rng);

        $teamIds = array_map(fn (TeamInput $t) => $t->id, $activeTeams);
        $byeCountByTeam = array_fill_keys($teamIds, 0);
        $homeCountByTeam = array_fill_keys($teamIds, 0);
        $awayCountByTeam = array_fill_keys($teamIds, 0);
        $homeVenueAppearancesByTeam = array_fill_keys($teamIds, 0);
        $lastVenueByTeam = [];
        $lastMeetingRoundByPair = [];

        $roundCandidates = [];

        foreach ($rounds as $index => $round) {
            $roundCandidates[] = $builder->build(
                $round,
                $activeTeams,
                $byeCountByTeam,
                $lastVenueByTeam,
                $lastMeetingRoundByPair,
                $homeCountByTeam,
                $awayCountByTeam,
                $homeVenueAppearancesByTeam,
                $index,
            );
        }

        return new ScheduleCandidate($roundCandidates);
    }
}
