<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * Every consecutive-same-venue occurrence costs one raw unit - a single
 * incident is still a real penalty and always scores as one (every
 * occurrence is reported in messages() regardless of team). On top of that,
 * a team hit more than once pays an *additional* unit per excess occurrence:
 * two different teams each hit once costs 2 raw units total (same as
 * always), but one team hit twice costs 3 raw units (2 for the occurrences
 * themselves, +1 repeat-offense surcharge) - a real repeat-offense pattern a
 * greedy, whole-schedule-blind search has no mechanism to avoid. "Twice"
 * means anywhere in the schedule, not necessarily back-to-back streaks of
 * 3+: rounds 1-2 then separately rounds 6-7 both count as 2 occurrences for
 * that team, same as three rounds running would.
 */
final class ConsecutiveVenueCriterion implements SoftCriterion
{
    use RecordsRoundViolations;

    /** @var array<int, int|null> team id => venue played at in the immediately preceding round */
    private array $lastVenueByTeam = [];

    /** @var array<int, int> team id => consecutive-same-venue occurrences */
    private array $venueStreakCountByTeam = [];

    private int $matchCount = 0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'consecutive_venue';
    }

    public function label(): string
    {
        return 'Consecutive rounds at the same venue';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $roundNumber = $roundIndex + 1;

        foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
            if (($this->lastVenueByTeam[$teamId] ?? null) === $match->venueId) {
                // Every occurrence is reported here (visibility - nothing is
                // silently hidden from the review screen just because it
                // happens to be the first one for this team), independent
                // of whether it ends up costing score points below.
                $this->venueStreakCountByTeam[$teamId] = ($this->venueStreakCountByTeam[$teamId] ?? 0) + 1;
                $this->messages[] = "{$this->context->teamLabel($teamId)} played consecutive rounds at the same venue ({$match->venueName}) around round {$roundNumber}.";
                $this->flagRoundViolation($roundIndex, $teamId);
            }
        }

        $this->lastVenueByTeam[$match->homeTeamId] = $match->venueId;
        $this->lastVenueByTeam[$match->awayTeamId] = $match->venueId;
        $this->matchCount++;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
        unset($this->lastVenueByTeam[$teamId]);
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
        $rawTotal = 0.0;

        foreach ($this->venueStreakCountByTeam as $count) {
            if ($count > 0) {
                $rawTotal += $count;
                $rawTotal += max(0, $count - 1);
            }
        }

        return $rawTotal / max(1, 2 * $this->matchCount);
    }

    public function epsilonUnit(): float
    {
        return 1 / max(1, 2 * $this->matchCount);
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
