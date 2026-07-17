<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\MtRng;
use App\Services\ScheduleGeneration\RoundBuilder;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\TeamInput;
use Tests\TestCase;

class RoundBuilderTest extends TestCase
{
    public function test_a_team_that_just_hosted_loses_the_home_venue_tie_break_even_with_fewer_cumulative_appearances()
    {
        // Team 1 has hosted far less often overall (0 vs 5 appearances), which
        // would win a pure "lowest cumulative count" tie-break - but team 1
        // was AT their own venue last round, so team 2 should host this round
        // instead. This is what actually prevents a team hosting several
        // rounds running (see plan.md for the real schedule this was found on).
        $teamA = new TeamInput(1, 'Team A', 100);
        $teamB = new TeamInput(2, 'Team B', 200);
        $slots = [new MatchSlotInput(901, 100, 'Venue A'), new MatchSlotInput(902, 200, 'Venue B')];
        $round = new RoundInput(1, new \DateTimeImmutable('2026-07-06'), $slots);

        $byeCountByTeam = [1 => 0, 2 => 0];
        $lastVenueByTeam = [1 => 100]; // team 1 played at their own venue last round.
        $lastMeetingRoundByPair = [];
        $homeCountByTeam = [1 => 0, 2 => 0];
        $awayCountByTeam = [1 => 0, 2 => 0];
        $homeVenueAppearancesByTeam = [1 => 0, 2 => 5];

        $result = (new RoundBuilder(new MtRng))->build(
            $round,
            [$teamA, $teamB],
            $byeCountByTeam,
            $lastVenueByTeam,
            $lastMeetingRoundByPair,
            $homeCountByTeam,
            $awayCountByTeam,
            $homeVenueAppearancesByTeam,
            1,
        );

        $this->assertCount(1, $result->matches);
        $this->assertSame(2, $result->matches[0]->homeTeamId, 'team 2 should host since team 1 just played at home last round');
        $this->assertSame(200, $result->matches[0]->venueId);
        $this->assertSame(902, $result->matches[0]->matchId);
    }
}
