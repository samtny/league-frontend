<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\DirectedPairKey;
use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * The away-role mirror of HomeCycleSpacingCriterion: "a team visits every
 * other active team once before revisiting the same team," scoped to that
 * team's OWN away-game sequence (home games don't count towards or against
 * it). Same caveat as the home-side sibling: reaching the ideal needs
 * roughly twice as many rounds as FullCycleSpacingCriterion's own ideal,
 * since only about half a team's games are away games, so it will rarely if
 * ever be fully reached in this app's typical short/partial seasons.
 */
final class AwayCycleSpacingCriterion implements SoftCriterion
{
    use RecordsRoundViolations;

    /** @var array<string, int> DirectedPairKey(visitor,host) => visitor's own away-game count at last visiting */
    private array $lastVisitIndexByDirectedPair = [];

    /** @var array<int, int> team id => away games played so far */
    private array $awayGameCountByTeam = [];

    private float $shortfallTotal = 0.0;

    private int $awayMatchCount = 0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'away_cycle_spacing';
    }

    public function label(): string
    {
        return 'Every team visited before revisiting the same team';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $roundNumber = $roundIndex + 1;
        $visitor = $match->awayTeamId;
        $host = $match->homeTeamId;
        $directedKey = DirectedPairKey::for($visitor, $host);
        $currentIndex = $this->awayGameCountByTeam[$visitor] ?? 0;
        $priorIndex = $this->lastVisitIndexByDirectedPair[$directedKey] ?? null;

        if ($priorIndex !== null) {
            $gap = $currentIndex - $priorIndex;
            $shortfall = max(0, $this->context->fullCycleGap - $gap);

            if ($shortfall > 0) {
                $this->shortfallTotal += $shortfall;
                $this->messages[] = "{$this->context->teamLabel($visitor)} played away at {$this->context->teamLabel($host)} again in round {$roundNumber} after playing away at only {$gap} other team(s), before visiting everyone else (needs {$this->context->fullCycleGap}+).";
                $this->flagRoundViolation($roundIndex, $visitor, $host);
            }
        }

        $this->lastVisitIndexByDirectedPair[$directedKey] = $currentIndex;
        $this->awayGameCountByTeam[$visitor] = $currentIndex + 1;
        $this->awayMatchCount++;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
    }

    public function finalize(): void
    {
    }

    public function penalty(GenerationConfig $config): float
    {
        return $this->weight($config) * $this->rawPenalty();
    }

    public function weight(GenerationConfig $config): float
    {
        return $config->tierWeight($this->key());
    }

    public function rawPenalty(): float
    {
        return $this->shortfallTotal / $this->divisor();
    }

    public function epsilonUnit(): float
    {
        return 1 / $this->divisor();
    }

    private function divisor(): int
    {
        return max(1, $this->awayMatchCount * max(1, $this->context->fullCycleGap));
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
