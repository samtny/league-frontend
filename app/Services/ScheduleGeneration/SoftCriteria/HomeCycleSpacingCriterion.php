<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\DirectedPairKey;
use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * Targets "a team hosts every other active team once before re-hosting the
 * same team" - scoped to that team's OWN home-game sequence specifically
 * (away games don't count towards or against it). The "clock" here is the
 * team's own home-game count, not the schedule's round index (contrast
 * FullCycleSpacingCriterion, which ignores role entirely and clocks off the
 * round index), so it naturally adjusts for how often a team actually
 * hosts. Reaching the ideal (fullCycleGap distinct home opponents between
 * repeats) needs roughly TWICE as many total rounds as
 * FullCycleSpacingCriterion's own ideal, since only about half a team's
 * games are home games - in this app's typical short/partial seasons it
 * will rarely if ever be fully reached, the same category of caveat
 * FullCycleSpacingCriterion already carries, just more pronounced.
 */
final class HomeCycleSpacingCriterion implements SoftCriterion
{
    use RecordsRoundViolations;

    /** @var array<string, int> DirectedPairKey(host,opponent) => host's own home-game count at last hosting */
    private array $lastHostIndexByDirectedPair = [];

    /** @var array<int, int> team id => home games played so far */
    private array $homeGameCountByTeam = [];

    private float $shortfallTotal = 0.0;

    private int $homeMatchCount = 0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'home_cycle_spacing';
    }

    public function label(): string
    {
        return 'Every team hosted before re-hosting the same team';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $roundNumber = $roundIndex + 1;
        $host = $match->homeTeamId;
        $opponent = $match->awayTeamId;
        $directedKey = DirectedPairKey::for($host, $opponent);
        $currentIndex = $this->homeGameCountByTeam[$host] ?? 0;
        $priorIndex = $this->lastHostIndexByDirectedPair[$directedKey] ?? null;

        if ($priorIndex !== null) {
            $gap = $currentIndex - $priorIndex;
            $shortfall = max(0, $this->context->fullCycleGap - $gap);

            if ($shortfall > 0) {
                $this->shortfallTotal += $shortfall;
                $this->messages[] = "{$this->context->teamLabel($host)} hosted {$this->context->teamLabel($opponent)} again in round {$roundNumber} after hosting only {$gap} other team(s) at home, before hosting everyone else (needs {$this->context->fullCycleGap}+).";
                $this->flagRoundViolation($roundIndex, $host, $opponent);
            }
        }

        $this->lastHostIndexByDirectedPair[$directedKey] = $currentIndex;
        $this->homeGameCountByTeam[$host] = $currentIndex + 1;
        $this->homeMatchCount++;
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
        return max(1, $this->homeMatchCount * max(1, $this->context->fullCycleGap));
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
