<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Series;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Rounds list on /admin/association/{a}/schedule/{s} appends a match
 * count to each Round's label so an admin can tell at a glance which Rounds
 * still need teams assigned.
 */
class ScheduleViewRoundsListTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(string $subdomain): array
    {
        $association = Association::factory()->create(['subdomain' => $subdomain]);

        $division = new Division(['name' => 'View Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'View Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'View Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => '2026-07-06', 'end_date' => '2026-07-20', 'weekday' => 'mon',
        ]);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        return compact('association', 'schedule', 'series', 'division');
    }

    private function createRound($schedule, $series, $division, string $name, string $date): Round
    {
        $round = new Round(['name' => $name]);
        $round->schedule_id = $schedule->id;
        $round->series_id = $series->id;
        $round->division_id = $division->id;
        $round->start_date = $date;
        $round->end_date = $date;
        $round->save();

        return $round;
    }

    private function createMatch($association, $schedule, $round, $homeTeamId = null, $awayTeamId = null): PLMatch
    {
        $match = new PLMatch(['name' => 'Match']);
        $match->association_id = $association->id;
        $match->series_id = $schedule->series_id;
        $match->division_id = $schedule->division_id;
        $match->schedule_id = $schedule->id;
        $match->round_id = $round->id;
        $match->start_date = $round->start_date;
        $match->end_date = $round->end_date;
        $match->home_team_id = $homeTeamId;
        $match->away_team_id = $awayTeamId;
        $match->save();

        return $match;
    }

    public function test_round_with_no_scheduled_matches_shows_no_matches_label()
    {
        ['association' => $association, 'schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('view-a');

        $round = $this->createRound($schedule, $series, $division, 'Round 1', '2026-07-06');
        $this->createMatch($association, $schedule, $round);

        $response = $this->get(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Round 1 - 07-06-2026 - No Matches', false);
    }

    public function test_round_with_scheduled_matches_shows_match_count_label()
    {
        ['association' => $association, 'schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('view-b');

        $homeTeam = Team::create(['name' => 'Home Team', 'association_id' => $association->id, 'active' => true]);
        $awayTeam = Team::create(['name' => 'Away Team', 'association_id' => $association->id, 'active' => true]);
        $homeTeam2 = Team::create(['name' => 'Home Team 2', 'association_id' => $association->id, 'active' => true]);
        $awayTeam2 = Team::create(['name' => 'Away Team 2', 'association_id' => $association->id, 'active' => true]);

        $round = $this->createRound($schedule, $series, $division, 'Round 1', '2026-07-06');
        $this->createMatch($association, $schedule, $round, $homeTeam->id, $awayTeam->id);
        $this->createMatch($association, $schedule, $round, $homeTeam2->id, $awayTeam2->id);

        $response = $this->get(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Round 1 - 07-06-2026 - 2 Matches', false);
    }
}
