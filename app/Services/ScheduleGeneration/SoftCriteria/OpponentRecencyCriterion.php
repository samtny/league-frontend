<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\OpponentGapCalculator;
use App\Services\ScheduleGeneration\PairKey;
use App\Services\ScheduleGeneration\ScoringContext;

final class OpponentRecencyCriterion implements SoftCriterion
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
        return 'opponent_recency';
    }

    public function label(): string
    {
        return 'Spacing between rematches';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;
        $pairKey = PairKey::for($home, $away);

        $gap = OpponentGapCalculator::sinceLastMeeting($roundIndex, $this->lastMeetingRoundByPair[$pairKey] ?? null);

        if ($gap !== null) {
            $shortfall = max(0, $this->context->idealGap - $gap);

            if ($shortfall > 0) {
                $this->shortfallTotal += $shortfall;
                $this->messages[] = "{$this->context->teamLabel($home)} and {$this->context->teamLabel($away)} rematched after only {$gap} round(s) (ideally {$this->context->idealGap}+).";
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
        return max(1, $this->matchCount * max(1, $this->context->idealGap));
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
