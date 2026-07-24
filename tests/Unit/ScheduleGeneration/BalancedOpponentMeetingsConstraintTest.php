<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\HardConstraints\BalancedOpponentMeetingsConstraint;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundCandidate;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\RoundRobinConstructor;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\ScoringContext;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class BalancedOpponentMeetingsConstraintTest extends TestCase
{
    /**
     * @param  array<int, int>  $homeVenueIdByTeamId  team id => venue id, one venue owned per team
     */
    private function teamsWithOwnVenues(array $homeVenueIdByTeamId): array
    {
        return array_map(
            fn (int $id, int $venueId) => new TeamInput($id, "Team {$id}", $venueId),
            array_keys($homeVenueIdByTeamId),
            array_values($homeVenueIdByTeamId),
        );
    }

    private function venuesFor(array $homeVenueIdByTeamId): array
    {
        return array_map(fn (int $venueId) => new VenueInput($venueId, "Venue {$venueId}"), array_values($homeVenueIdByTeamId));
    }

    /**
     * @param  VenueInput[]  $venues
     * @return RoundInput[]
     */
    private function rounds(int $count, array $venues): array
    {
        $rounds = [];
        $date = new \DateTimeImmutable('2026-07-06');
        $matchId = 1;

        for ($i = 0; $i < $count; $i++) {
            $slots = array_map(
                fn (VenueInput $venue) => new MatchSlotInput($matchId++, $venue->id, $venue->name),
                $venues,
            );
            $rounds[] = new RoundInput($i + 1, $date, $slots);
            $date = $date->add(new \DateInterval('P7D'));
        }

        return $rounds;
    }

    /**
     * @return string[] violation messages
     */
    private function violationsFor(ScheduleCandidate $candidate, array $activeTeams, array $activeVenues): array
    {
        $context = ScoringContext::build($activeTeams, $activeVenues);
        $constraint = new BalancedOpponentMeetingsConstraint($context);

        foreach ($candidate->rounds as $roundIndex => $round) {
            $constraint->startRound($roundIndex);

            foreach ($round->matches as $match) {
                $constraint->observeMatch($roundIndex, $match);
            }
        }

        return $constraint->violations();
    }

    public function test_accepts_round_robin_constructor_output_across_a_spread_of_sizes()
    {
        // RoundRobinConstructor satisfies balanced opponent meetings by
        // construction (plan.md §4) - this locks that guarantee in across
        // even and odd team counts, and both a single-cycle season (rounds =
        // n-1) and a multi-cycle season (rounds > n-1, forcing rematches).
        foreach ([4, 5, 6, 7, 8, 10] as $n) {
            $homeVenueIdByTeamId = [];
            for ($i = 1; $i <= $n; $i++) {
                $homeVenueIdByTeamId[$i] = 100 + $i;
            }

            $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
            $venues = $this->venuesFor($homeVenueIdByTeamId);

            foreach ([$n - 1, ($n - 1) * 2] as $roundCount) {
                $candidate = (new RoundRobinConstructor(new SeededRng($n)))->construct(
                    $this->rounds($roundCount, $venues),
                    $teams,
                    $venues,
                );

                $this->assertNotNull($candidate, "n={$n} rounds={$roundCount} should be eligible for RoundRobinConstructor");

                $violations = $this->violationsFor($candidate, $teams, $venues);

                $this->assertSame(
                    [],
                    $violations,
                    "n={$n} rounds={$roundCount} RoundRobinConstructor output should never violate balanced opponent meetings",
                );
            }
        }
    }

    public function test_rejects_the_degenerate_schedule_where_one_pair_meets_far_too_often_and_another_never_meets()
    {
        // The exact fixture from plan.md ("Size-Aware Schedule Generation")
        // §1e: an exhaustive search with consecutive_venue as the only
        // enabled criterion and no structural round-robin constraint
        // returned this 4-team, 6-round schedule - scored PERFECTLY (zero
        // venue repeats, zero breaks) while being a worthless season: A and
        // B meet four times, A and C never meet at all. This is the
        // permanent regression fixture for why BalancedOpponentMeetingsConstraint
        // has to exist as a HARD constraint rather than trusting the search
        // to avoid this on its own.
        //
        //   W1  B @ A    D @ C
        //   W2  A @ D    C @ B
        //   W3  B @ A    D @ C
        //   W4  A @ B    C @ D
        //   W5  D @ A    B @ C
        //   W6  A @ B    C @ D
        //
        // A=1, B=2, C=3, D=4. 12 total matches, 4 teams -> P=6 pairs,
        // floor(12/6)=ceil(12/6)=2 expected per pair. A-B and C-D each meet
        // 4 times (too many); A-C and B-D each meet 0 times (too few); A-D
        // and B-C meet 2 times each (exactly right, not flagged).
        $teams = [
            new TeamInput(1, 'Team A'),
            new TeamInput(2, 'Team B'),
            new TeamInput(3, 'Team C'),
            new TeamInput(4, 'Team D'),
        ];
        $venues = [new VenueInput(10, 'Venue 10')];
        $date = new \DateTimeImmutable('2026-07-06');

        $candidate = new ScheduleCandidate([
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 1, 2), new MatchCandidate(10, 'Venue 10', 3, 4)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 4, 1), new MatchCandidate(10, 'Venue 10', 2, 3)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 1, 2), new MatchCandidate(10, 'Venue 10', 3, 4)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 2, 1), new MatchCandidate(10, 'Venue 10', 4, 3)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 1, 4), new MatchCandidate(10, 'Venue 10', 3, 2)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 2, 1), new MatchCandidate(10, 'Venue 10', 4, 3)], []),
        ]);

        $violations = $this->violationsFor($candidate, $teams, $venues);

        $this->assertCount(4, $violations, 'A-B, C-D (too many) and A-C, B-D (too few) should all be flagged');

        $messages = implode(' ', $violations);
        $this->assertStringContainsString('Team A and Team B met 4 time(s)', $messages);
        $this->assertStringContainsString('Team C and Team D met 4 time(s)', $messages);
        $this->assertStringContainsString('Team A and Team C met 0 time(s)', $messages);
        $this->assertStringContainsString('Team B and Team D met 0 time(s)', $messages);
        // A-D and B-C meet exactly twice (the expected count) - not flagged.
        $this->assertStringNotContainsString('Team A and Team D met', $messages);
        $this->assertStringNotContainsString('Team B and Team C met', $messages);

        // Violation messages use team NAMES, never raw ids - locked in
        // convention, see ScheduleScorerTest::test_violation_messages_use_team_names_not_generic_ids.
        $this->assertStringNotContainsString('#1', $messages);
        $this->assertStringNotContainsString('#2', $messages);
        $this->assertStringNotContainsString('#3', $messages);
        $this->assertStringNotContainsString('#4', $messages);
    }

    public function test_a_clean_single_round_has_no_violations()
    {
        // 1 match observed, 1 pair possible -> floor(1/1)=ceil(1/1)=1, and
        // the only pair met exactly once. Also covers the "not enough
        // matches/pairs yet" early-return paths staying silent rather than
        // false-flagging early in a schedule.
        $teams = [new TeamInput(1, 'Team 1'), new TeamInput(2, 'Team 2')];
        $venues = [new VenueInput(10, 'Venue 10')];

        $candidate = new ScheduleCandidate([
            new RoundCandidate(new \DateTimeImmutable('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $this->assertSame([], $this->violationsFor($candidate, $teams, $venues));
    }

    public function test_flag_disabled_means_scheduler_does_not_register_the_constraint_at_all()
    {
        // Same degenerate fixture as the dedicated rejection test above,
        // scored THROUGH ScheduleScorer directly (the actual integration
        // point) rather than by calling the constraint class in isolation -
        // this is what actually proves GenerationConfig::$enforceBalancedOpponents
        // controls registration, not merely that the constraint class can be
        // constructed and ignored.
        $teams = [
            new TeamInput(1, 'Team A'),
            new TeamInput(2, 'Team B'),
            new TeamInput(3, 'Team C'),
            new TeamInput(4, 'Team D'),
        ];
        $venues = [new VenueInput(10, 'Venue 10')];
        $date = new \DateTimeImmutable('2026-07-06');

        $candidate = new ScheduleCandidate([
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 1, 2), new MatchCandidate(10, 'Venue 10', 3, 4)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 4, 1), new MatchCandidate(10, 'Venue 10', 2, 3)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 1, 2), new MatchCandidate(10, 'Venue 10', 3, 4)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 2, 1), new MatchCandidate(10, 'Venue 10', 4, 3)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 1, 4), new MatchCandidate(10, 'Venue 10', 3, 2)], []),
            new RoundCandidate($date, [new MatchCandidate(10, 'Venue 10', 2, 1), new MatchCandidate(10, 'Venue 10', 4, 3)], []),
        ]);

        $scorer = new ScheduleScorer;

        $enforcedReport = $scorer->score($candidate, $teams, $venues, new GenerationConfig(softCriteria: [], enforceBalancedOpponents: true));
        $this->assertFalse($enforcedReport->hardConstraintsSatisfied, 'default (enforced) config must reject the degenerate schedule');
        $this->assertNotEmpty(array_filter(
            $enforcedReport->hardViolations,
            fn (string $v) => str_contains($v, 'met 4 time(s)') || str_contains($v, 'met 0 time(s)'),
        ));

        $disabledReport = $scorer->score($candidate, $teams, $venues, new GenerationConfig(softCriteria: [], enforceBalancedOpponents: false));
        $this->assertTrue($disabledReport->hardConstraintsSatisfied, 'disabling the flag must skip registering the constraint entirely');
        $this->assertSame([], $disabledReport->hardViolations);
    }

    public function test_default_generation_config_enforces_balanced_opponent_meetings()
    {
        $this->assertTrue((new GenerationConfig)->enforceBalancedOpponents);
    }

    /**
     * Regression: this shipped as a 500 on the generate-matches screen.
     *
     * Every fixture in this file (and every other generation test) hand-builds
     * $activeTeams as a zero-indexed list, but the real caller derives it from
     * `$association->activeTeams->filter(...)`, and Collection::filter()
     * PRESERVES keys - so production passed in something keyed e.g.
     * [0, 1, 3, 5] while violations() iterated positions 0..teamCount-1 and
     * read a missing key. The whole suite was green throughout.
     *
     * Non-sequential keys are therefore an input this constraint must accept,
     * not an input the caller is trusted to have normalised - even though the
     * caller now also calls values(), because one defence at each end is what
     * keeps this from silently returning if some future caller forgets.
     */
    public function test_accepts_active_teams_with_non_sequential_array_keys()
    {
        $homeVenueIdByTeamId = [1 => 101, 2 => 102, 3 => 103, 4 => 104];
        $teams = $this->teamsWithOwnVenues($homeVenueIdByTeamId);
        $venues = $this->venuesFor($homeVenueIdByTeamId);

        $candidate = (new RoundRobinConstructor(new SeededRng(4)))->construct(
            $this->rounds(3, $venues),
            $teams,
            $venues,
        );

        // Exactly what Collection::filter()->map()->all() hands back once
        // anything has been filtered out: same teams, same order, gappy keys.
        $gappyKeyedTeams = [0 => $teams[0], 1 => $teams[1], 3 => $teams[2], 5 => $teams[3]];

        $this->assertSame(
            [],
            $this->violationsFor($candidate, $gappyKeyedTeams, $venues),
            'A balanced schedule must stay balanced when $activeTeams arrives with non-sequential keys',
        );
    }
}
