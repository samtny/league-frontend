<?php

namespace App\Services\ScheduleGeneration;

use App\Services\ScheduleGeneration\HardConstraints\AwayTeamAtOwnVenueConstraint;
use App\Services\ScheduleGeneration\HardConstraints\ByeAndMatchConflictConstraint;
use App\Services\ScheduleGeneration\HardConstraints\DoubleBookedTeamConstraint;
use App\Services\ScheduleGeneration\HardConstraints\HomeTeamAtAnotherTeamsVenueConstraint;
use App\Services\ScheduleGeneration\HardConstraints\InactiveTeamConstraint;
use App\Services\ScheduleGeneration\HardConstraints\InactiveVenueConstraint;
use App\Services\ScheduleGeneration\SoftCriteria\SoftCriterionRegistry;

/**
 * Pure, randomness-free scoring: replays a candidate round by round, feeding
 * every match and bye to a fixed set of hard constraints (any violation
 * invalidates the candidate) and soft criteria (weighted penalties summed
 * into a score), so it can be used both to score generator output and to
 * unit-test hand-built candidates directly.
 */
final class ScheduleScorer
{
    /**
     * @param TeamInput[] $activeTeams
     * @param VenueInput[] $activeVenues
     */
    public function score(ScheduleCandidate $candidate, array $activeTeams, array $activeVenues, GenerationConfig $config): GenerationReport
    {
        $context = ScoringContext::build($activeTeams, $activeVenues);

        $hardConstraints = [
            new InactiveTeamConstraint($context),
            new DoubleBookedTeamConstraint($context),
            new InactiveVenueConstraint($context),
            new AwayTeamAtOwnVenueConstraint($context),
            new HomeTeamAtAnotherTeamsVenueConstraint($context),
            new ByeAndMatchConflictConstraint($context),
        ];

        $softCriteria = SoftCriterionRegistry::build($config->flatSoftCriteria(), $context);

        foreach ($candidate->rounds as $roundIndex => $round) {
            foreach ($hardConstraints as $constraint) {
                $constraint->startRound($roundIndex);
            }

            foreach ($round->matches as $match) {
                foreach ($hardConstraints as $constraint) {
                    $constraint->observeMatch($roundIndex, $match);
                }

                foreach ($softCriteria as $criterion) {
                    $criterion->observeMatch($roundIndex, $match);
                }
            }

            foreach ($round->byeTeamIds as $teamId) {
                foreach ($hardConstraints as $constraint) {
                    $constraint->observeBye($roundIndex, $teamId);
                }

                foreach ($softCriteria as $criterion) {
                    $criterion->observeBye($roundIndex, $teamId);
                }
            }
        }

        foreach ($softCriteria as $criterion) {
            $criterion->finalize();
        }

        $hardViolations = [];

        foreach ($hardConstraints as $constraint) {
            array_push($hardViolations, ...$constraint->violations());
        }

        $score = 0.0;
        $softViolationsByCriterion = [];
        $softCriteriaScores = [];

        foreach ($softCriteria as $criterion) {
            $criterionScore = $criterion->penalty($config);
            $score += $criterionScore;

            $softCriteriaScores[] = [
                'key' => $criterion->key(),
                'label' => $criterion->label(),
                'score' => $criterionScore,
                'weight' => $criterion->weight($config),
                'raw' => $criterion->rawPenalty(),
                'epsilonUnit' => $criterion->epsilonUnit(),
            ];

            $messages = $criterion->messages();

            if (! empty($messages)) {
                $softViolationsByCriterion[$criterion->key()] = $messages;
            }
        }

        return new GenerationReport(
            hardConstraintsSatisfied: empty($hardViolations),
            hardViolations: $hardViolations,
            softViolationsByCriterion: $softViolationsByCriterion,
            score: $score,
            degenerate: false,
            softCriteriaScores: $softCriteriaScores,
        );
    }
}
