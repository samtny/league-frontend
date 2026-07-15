<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Result;
use App\ResultSubmission;
use App\Round;
use App\Schedule;
use App\Series;
use App\Team;
use App\User;
use App\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Schedule is soft-deleted but Round/PLMatch/Result/ResultSubmission are not,
 * so a plain soft delete never trips the DB's ON DELETE CASCADE chain
 * (matches.round_id -> rounds, results/result_submissions.match_id ->
 * matches) - no row is ever physically removed. Schedule::booted() bridges
 * that gap by hard-deleting its rounds on delete, letting the existing FK
 * cascades clean up everything downstream.
 */
class ScheduleDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(string $subdomain): array
    {
        $association = Association::factory()->create(['subdomain' => $subdomain]);

        $venue = Venue::create(['name' => 'Deletion Venue', 'association_id' => $association->id]);
        $homeTeam = Team::create(['name' => 'Home Team', 'association_id' => $association->id, 'venue_id' => $venue->id]);
        $awayTeam = Team::create(['name' => 'Away Team', 'association_id' => $association->id, 'venue_id' => $venue->id]);

        $division = new Division(['name' => 'Deletion Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Deletion Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Deletion Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(),
        ]);

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $series->id;
        $round->division_id = $division->id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $match = new PLMatch(['name' => 'Home vs Away']);
        $match->association_id = $association->id;
        $match->series_id = $series->id;
        $match->division_id = $division->id;
        $match->schedule_id = $schedule->id;
        $match->round_id = $round->id;
        $match->venue_id = $venue->id;
        $match->home_team_id = $homeTeam->id;
        $match->away_team_id = $awayTeam->id;
        $match->start_date = $round->start_date;
        $match->end_date = $round->end_date;
        $match->save();

        $result = Result::create([
            'match_id' => $match->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'home_team_score' => 5,
            'away_team_score' => 2,
        ]);

        $submission = new ResultSubmission;
        $submission->association_id = $association->id;
        $submission->schedule_id = $schedule->id;
        $submission->match_id = $match->id;
        $submission->home_team_score = 5;
        $submission->away_team_score = 2;
        $submission->win_team_id = $homeTeam->id;
        $submission->save();

        return compact('association', 'schedule', 'round', 'match', 'result', 'submission');
    }

    private function actingAsAdmin(): void
    {
        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);
    }

    public function test_deleting_schedule_cascades_to_rounds_matches_and_results()
    {
        ['association' => $association, 'schedule' => $schedule, 'round' => $round, 'match' => $match, 'result' => $result, 'submission' => $submission]
            = $this->buildFixture('deletion-a');

        $this->actingAsAdmin();

        $response = $this->post(route('schedule.delete', ['association' => $association, 'schedule' => $schedule]));

        $response->assertRedirect(route('series.schedules', ['association' => $association, 'series' => $schedule->series]));

        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('rounds', ['id' => $round->id]);
        $this->assertDatabaseMissing('matches', ['id' => $match->id]);
        $this->assertDatabaseMissing('results', ['id' => $result->id]);
        $this->assertDatabaseMissing('result_submissions', ['id' => $submission->id]);
    }

    public function test_deleting_schedule_directly_via_model_cascades_too()
    {
        ['schedule' => $schedule, 'round' => $round, 'match' => $match, 'result' => $result, 'submission' => $submission]
            = $this->buildFixture('deletion-b');

        $schedule->delete();

        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('rounds', ['id' => $round->id]);
        $this->assertDatabaseMissing('matches', ['id' => $match->id]);
        $this->assertDatabaseMissing('results', ['id' => $result->id]);
        $this->assertDatabaseMissing('result_submissions', ['id' => $submission->id]);
    }

    public function test_deleting_a_round_still_cascades_to_its_own_matches()
    {
        ['round' => $round, 'match' => $match, 'result' => $result, 'submission' => $submission] = $this->buildFixture('deletion-c');

        $round->delete();

        $this->assertDatabaseMissing('rounds', ['id' => $round->id]);
        $this->assertDatabaseMissing('matches', ['id' => $match->id]);
        $this->assertDatabaseMissing('results', ['id' => $result->id]);
        $this->assertDatabaseMissing('result_submissions', ['id' => $submission->id]);
    }
}
