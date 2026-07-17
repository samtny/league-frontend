<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\OpponentGapCalculator;
use App\Services\ScheduleGeneration\PairKey;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * Targets literally "every other active team faced once before a rematch" -
 * the ideal gap is a full single round-robin cycle
 * (ScoringContext::$fullCycleGap, activeTeamCount - 1 rounds), not merely
 * "some" spacing. A shorter schedule than that (see SlotCount/RoundInput -
 * round count is date-range driven, independent of team count, so a season
 * can easily be shorter than one full cycle) simply can't reach the ideal
 * for every pair; the criterion still rewards getting as close to it as the
 * available rounds allow, same as any other soft criterion whose ideal isn't
 * always attainable.
 */
final class FullCycleSpacingCriterion implements SoftCriterion
{
    /** @var array<string, int> pair key => round index of last meeting */
    private array $lastMeetingRoundByPair = [];

    private float $shortfallTotal = 0.0;

    private int $matchCount = 0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'full_cycle_spacing';
    }

    public function label(): string
    {
        return 'Every team faced before a rematch';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $roundNumber = $roundIndex + 1;
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;
        $pairKey = PairKey::for($home, $away);

        $gap = OpponentGapCalculator::sinceLastMeeting($roundIndex, $this->lastMeetingRoundByPair[$pairKey] ?? null);

        if ($gap !== null) {
            $shortfall = max(0, $this->context->fullCycleGap - $gap);

            if ($shortfall > 0) {
                $this->shortfallTotal += $shortfall;
                $this->messages[] = "{$this->context->teamLabel($home)} and {$this->context->teamLabel($away)} rematched in round {$roundNumber} after only {$gap} round(s), before facing every other team (needs {$this->context->fullCycleGap}+).";
            }
        }

        $this->lastMeetingRoundByPair[$pairKey] = $roundIndex;
        $this->matchCount++;
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
        return max(1, $this->matchCount * max(1, $this->context->fullCycleGap));
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
