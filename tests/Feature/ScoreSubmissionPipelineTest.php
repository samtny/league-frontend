<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Series;
use App\Team;
use App\User;
use App\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage of the app's core business logic: an anonymous visitor
 * submits a match score through the public wizard (/submit -> step2 -> step3
 * -> step4[ -> step5 if tied]), an admin approves the resulting
 * ResultSubmission, and the standings page (which reads directly from the
 * team_results table) reflects it. Several links in this chain were found
 * broken by hand during a controller refactor (orphaned ResultsController,
 * Round.division_id never populated, a null-submission crash in step5) with
 * no test coverage catching any of them - this is that coverage.
 */
class ScoreSubmissionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(string $subdomain): array
    {
        $association = Association::factory()->create(['subdomain' => $subdomain]);

        $venue = Venue::create(['name' => 'Pipeline Venue', 'association_id' => $association->id]);
        $homeTeam = Team::create(['name' => 'Home Team', 'association_id' => $association->id, 'venue_id' => $venue->id]);
        $awayTeam = Team::create(['name' => 'Away Team', 'association_id' => $association->id, 'venue_id' => $venue->id]);

        $division = new Division(['name' => 'Pipeline Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Pipeline Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Pipeline Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
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

        return compact('association', 'homeTeam', 'awayTeam', 'match');
    }

    public function test_wizard_to_approval_to_standings_with_a_clear_winner()
    {
        ['association' => $association, 'homeTeam' => $homeTeam, 'awayTeam' => $awayTeam, 'match' => $match]
            = $this->buildFixture('pipeline-a');
        $base = 'http://pipeline-a.pinballleague.org';

        $this->get($base.'/submit')->assertStatus(200);

        $this->post($base.'/submit/step2', ['division_id' => $match->division_id])->assertStatus(200);
        $this->post($base.'/submit/step3', ['match_id' => $match->id])->assertStatus(200);

        $step4 = $this->post($base.'/submit/step4', [
            'match_id' => $match->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'home_team_score' => 5,
            'away_team_score' => 2,
        ]);
        $step4->assertStatus(200);

        $this->assertDatabaseHas('result_submissions', [
            'match_id' => $match->id,
            'home_team_score' => 5,
            'away_team_score' => 2,
            'win_team_id' => $homeTeam->id,
        ]);

        $submission = \App\ResultSubmission::where('match_id', $match->id)->first();
        $this->assertFalse((bool) $submission->approved);
        $submissionId = $submission->id;

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        $approve = $this->post(route('result_submission.update', ['association' => $association, 'id' => $submissionId]), []);
        $approve->assertRedirect();

        $this->assertDatabaseHas('result_submissions', ['id' => $submissionId, 'approved' => 1]);
        $this->assertDatabaseHas('results', ['match_id' => $match->id, 'home_team_score' => 5, 'away_team_score' => 2]);
        $this->assertDatabaseHas('team_results', ['match_id' => $match->id, 'team_id' => $homeTeam->id, 'win' => 1, 'loss' => 0, 'points' => 5]);
        $this->assertDatabaseHas('team_results', ['match_id' => $match->id, 'team_id' => $awayTeam->id, 'win' => 0, 'loss' => 1, 'points' => 2]);

        $standings = $this->get($base.'/standings');
        $standings->assertStatus(200);
        $standings->assertSee('Home Team');
        $standings->assertSee('1 - 0');
        $standings->assertSee('Away Team');
        $standings->assertSee('0 - 1');
    }

    public function test_wizard_handles_tied_scores_via_step5_winner_choice()
    {
        ['association' => $association, 'homeTeam' => $homeTeam, 'awayTeam' => $awayTeam, 'match' => $match]
            = $this->buildFixture('pipeline-b');
        $base = 'http://pipeline-b.pinballleague.org';

        $this->post($base.'/submit/step2', ['division_id' => $match->division_id]);
        $this->post($base.'/submit/step3', ['match_id' => $match->id]);

        $step4 = $this->post($base.'/submit/step4', [
            'match_id' => $match->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'home_team_score' => 3,
            'away_team_score' => 3,
        ]);
        // Tied score -> choose-winner step, not the thanks page yet.
        $step4->assertStatus(200);
        $step4->assertSee(__('Who Won?'));

        $submission = \App\ResultSubmission::where('match_id', $match->id)->first();
        $this->assertNull($submission->win_team_id);

        $step5 = $this->post($base.'/submit/step5', [
            'submission_id' => $submission->id,
            'win_team_id' => $awayTeam->id,
        ]);
        $step5->assertStatus(200);

        $this->assertDatabaseHas('result_submissions', [
            'id' => $submission->id,
            'win_team_id' => $awayTeam->id,
        ]);
    }
}
