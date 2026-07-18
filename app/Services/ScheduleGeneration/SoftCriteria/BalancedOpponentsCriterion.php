<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\PairKey;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * Penalizes a pair of teams facing each other more than their fair share of
 * matches. The idealized round-robin rate - roundsSeen / fullCycleGap
 * (fullCycleGap = activeTeamCount - 1, the same "one full cycle" unit
 * FullCycleSpacingCriterion targets) - is a fraction (e.g. 2 rounds into a
 * 4-team, 3-round cycle is 2/3 of a meeting per pair), so the allowed count
 * before penalizing is its ceiling, not the raw fraction: with 4 teams and
 * only 1 round played, every pair that met once is fully expected (ceil(1/3)
 * = 1), even though 1/3 < 1 - comparing against the raw fraction would
 * falsely flag every single-round schedule. FullCycleSpacingCriterion
 * penalizes a rematch that comes too SOON after the last meeting; this
 * penalizes a pair meeting too OFTEN overall relative to the rest of the
 * schedule - complementary, not redundant. Only the excess above that
 * ceiling is penalized; meeting fewer times than expected contributes
 * nothing.
 */
final class BalancedOpponentsCriterion implements SoftCriterion
{
    /** @var array<string, int> pair key => number of times these two teams have played */
    private array $matchesByPair = [];

    private int $matchCount = 0;

    private int $roundsSeen = 0;

    private float $excessTotal = 0.0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'balanced_opponents';
    }

    public function label(): string
    {
        return 'Balanced matchups between opponents';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $pairKey = PairKey::for($match->homeTeamId, $match->awayTeamId);

        $this->matchesByPair[$pairKey] = ($this->matchesByPair[$pairKey] ?? 0) + 1;
        $this->matchCount++;
        $this->roundsSeen = max($this->roundsSeen, $roundIndex + 1);
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
        $this->roundsSeen = max($this->roundsSeen, $roundIndex + 1);
    }

    public function finalize(): void
    {
        if (empty($this->matchesByPair) || $this->context->fullCycleGap === 0) {
            return;
        }

        $allowed = (int) ceil($this->roundsSeen / $this->context->fullCycleGap);

        foreach ($this->matchesByPair as $pairKey => $count) {
            $excess = $count - $allowed;

            if ($excess > 0) {
                $this->excessTotal += $excess;
                [$teamAId, $teamBId] = array_map('intval', explode('-', $pairKey));
                $this->messages[] = sprintf(
                    '%s and %s played %d times, more than the expected %d time(s) over %d round(s) with %d teams.',
                    $this->context->teamLabel($teamAId),
                    $this->context->teamLabel($teamBId),
                    $count,
                    $allowed,
                    $this->roundsSeen,
                    count($this->context->activeTeamIds)
                );
            }
        }
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
        return $this->excessTotal / max(1, $this->matchCount);
    }

    public function epsilonUnit(): float
    {
        return 1 / max(1, $this->matchCount);
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public function roundViolations(): array
    {
        // Excess-meeting count for a pair is computed once, over the whole
        // schedule, in finalize() - no single round is "the" violating one.
        return [];
    }
}
