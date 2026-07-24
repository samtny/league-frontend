<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\PairKey;
use App\Services\ScheduleGeneration\ScoringContext;
use App\Services\ScheduleGeneration\TeamInput;

/**
 * A whole-schedule constraint, not a per-round one: it accumulates a
 * per-unordered-pair meeting count across every round observed, then
 * evaluates the result once from violations() (called after ScheduleScorer's
 * round loop finishes) rather than flagging anything mid-round the way
 * startRound()/observeMatch() do for the other HardConstraints in this
 * namespace.
 *
 * Rule: with M total matches across the whole schedule and P =
 * C(activeTeamCount, 2) unordered pairs of active teams, every pair must
 * meet between floor(M/P) and ceil(M/P) times inclusive - the same "as equal
 * as an integer division allows" balance a plain round-robin produces for
 * free. A pair that meets zero times while M is large enough that
 * floor(M/P) > 0 is just as much a violation as a pair that meets far too
 * often; both directions matter; see the fixture below.
 *
 * This is deliberately NOT the same thing as the existing
 * BalancedOpponentsCriterion soft criterion, which penalizes a pair
 * exceeding its round-by-round fair share (roundsSeen / fullCycleGap,
 * ceiling'd) as the schedule is built up - useful for guiding search
 * mid-flight, but it never checks the *other* direction (a pair that meets
 * too rarely) and it's a soft, tunable-weight penalty rather than a gate. A
 * candidate can score zero on BalancedOpponentsCriterion and still contain
 * the exact degenerate case this hard constraint exists to catch (see
 * plan.md "Size-Aware Schedule Generation" §1e: an exhaustive search with no
 * structural constraint returned a 4-team schedule where one pair played
 * four times and another pair never played at all, and scored PERFECTLY on
 * every enabled soft criterion because none of them forbid it).
 *
 * Registration is conditional - see GenerationConfig::$enforceBalancedOpponents
 * for why this can't simply be added to ScheduleScorer's hard-constraint list
 * unconditionally yet.
 */
final class BalancedOpponentMeetingsConstraint implements HardConstraint
{
    /** @var array<string, int> pair key => number of times these two teams have played */
    private array $matchesByPair = [];

    private int $matchCount = 0;

    public function __construct(
        private readonly ScoringContext $context,
    ) {}

    public function key(): string
    {
        return 'balanced_opponent_meetings';
    }

    public function label(): string
    {
        return 'Every pair of teams meets a balanced number of times';
    }

    public function startRound(int $roundIndex): void {}

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $pairKey = PairKey::for($match->homeTeamId, $match->awayTeamId);

        $this->matchesByPair[$pairKey] = ($this->matchesByPair[$pairKey] ?? 0) + 1;
        $this->matchCount++;
    }

    public function observeBye(int $roundIndex, int $teamId): void {}

    /**
     * Evaluated lazily here rather than incrementally, since "expected
     * count" (floor(M/P)/ceil(M/P)) depends on the FINAL total match count
     * M, which isn't known until every round has been observed - unlike the
     * other HardConstraints in this namespace, which can flag a violation
     * the moment it happens.
     */
    public function violations(): array
    {
        // array_values, because the pairwise loop below indexes this
        // positionally as 0..teamCount-1 and array_map preserves whatever
        // keys $activeTeams arrived with - which is not a zero-indexed list
        // when the caller built it from a filtered Eloquent Collection.
        $activeTeamIds = array_values(array_map(fn (TeamInput $team) => $team->id, $this->context->activeTeams));
        $teamCount = count($activeTeamIds);
        $pairCount = (int) ($teamCount * ($teamCount - 1) / 2);

        // Fewer than 2 active teams: no pairs exist, nothing to balance.
        // Zero matches observed: nothing played yet, trivially balanced.
        if ($pairCount === 0 || $this->matchCount === 0) {
            return [];
        }

        $minAllowed = (int) floor($this->matchCount / $pairCount);
        $maxAllowed = (int) ceil($this->matchCount / $pairCount);

        $violations = [];

        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                $teamAId = $activeTeamIds[$i];
                $teamBId = $activeTeamIds[$j];
                $count = $this->matchesByPair[PairKey::for($teamAId, $teamBId)] ?? 0;

                if ($count < $minAllowed || $count > $maxAllowed) {
                    $violations[] = sprintf(
                        '%s and %s met %d time(s); expected between %d and %d time(s) across %d total match(es) among %d teams.',
                        $this->context->teamLabel($teamAId),
                        $this->context->teamLabel($teamBId),
                        $count,
                        $minAllowed,
                        $maxAllowed,
                        $this->matchCount,
                        $teamCount,
                    );
                }
            }
        }

        return $violations;
    }
}
