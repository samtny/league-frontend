<?php

namespace Tests\Feature;

use App\Association;
use App\ContactSubmission;
use App\Division;
use App\Member;
use App\Round;
use App\Series;
use App\Team;
use App\User;
use App\Venue;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cheap, broad smoke coverage: hit every named GET route with real bound
 * models and assert it never 500s. Deliberately shallow (no assertions
 * about page content) - its only job is catching "route points at a
 * controller method / view that doesn't exist", a class of bug found
 * several times by hand during a controller refactor before this test
 * existed (ResultsController::update, UsersController::create, the missing
 * association.undelete view, ...).
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_get_routes_do_not_crash()
    {
        $this->seed();

        $admin = User::factory()->create(['email' => 'smoke-admin@example.com']);
        Bouncer::assign('superadmin')->to($admin);

        $association = Association::factory()->create(['subdomain' => 'smoke']);

        $division = new Division(['name' => 'Smoke Division']);
        $division->association_id = $association->id;
        $division->save();

        $venue = Venue::create(['name' => 'Smoke Venue', 'association_id' => $association->id]);
        $team = Team::create(['name' => 'Smoke Team', 'association_id' => $association->id, 'venue_id' => $venue->id]);
        $member = Member::create(['name' => 'Smoke Member', 'role' => 'Player', 'order' => 1, 'team_id' => $team->id, 'association_id' => $association->id]);

        $series = Series::create(['name' => 'Smoke Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Smoke Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(),
        ]);

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $series->id;
        $round->division_id = $division->id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $contactSubmission = ContactSubmission::create([
            'email' => 'smoke@example.com', 'reason' => 'other', 'comment' => 'smoke',
            'association_id' => $association->id,
        ]);

        $targetUser = User::factory()->create(['email' => 'smoke-target@example.com']);

        $this->actingAs($admin);

        $routes = [
            ['venue.create', ['association' => $association]],
            ['venue.edit', ['association' => $association, 'venue' => $venue]],
            ['venue.deleteConfirm', ['association' => $association, 'venue' => $venue]],
            ['team.create', ['association' => $association]],
            ['team.edit', ['association' => $association, 'team' => $team]],
            ['team.deleteConfirm', ['association' => $association, 'team' => $team]],
            ['team.roster', ['association' => $association, 'team' => $team]],
            ['member.create', ['association' => $association, 'team' => $team]],
            ['member.edit', ['association' => $association, 'member' => $member]],
            ['member.deleteConfirm', ['association' => $association, 'member' => $member]],
            ['division.create', ['association' => $association]],
            ['division.edit', ['association' => $association, 'division' => $division]],
            ['division.deleteConfirm', ['association' => $association, 'division' => $division]],
            ['series.create', ['association' => $association]],
            ['association.series.archived', ['association' => $association]],
            ['series.view', ['association' => $association, 'series' => $series]],
            ['series.edit', ['association' => $association, 'series' => $series]],
            ['series.deleteConfirm', ['association' => $association, 'series' => $series]],
            ['series.schedules', ['association' => $association, 'series' => $series]],
            ['schedule.create', ['association' => $association, 'series' => $series]],
            ['schedule.view', ['association' => $association, 'schedule' => $schedule]],
            ['schedule.edit', ['association' => $association, 'schedule' => $schedule]],
            ['schedule.delete-confirm', ['association' => $association, 'schedule' => $schedule]],
            ['schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]],
            ['schedule.generate-matches.select', ['association' => $association, 'schedule' => $schedule]],
            ['round.create', ['association' => $association, 'schedule' => $schedule]],
            ['round.edit', ['association' => $association, 'schedule' => $schedule, 'round' => $round]],
            ['round.delete-confirm', ['association' => $association, 'schedule' => $schedule, 'round' => $round]],
            ['result_submissions.list', ['association' => $association]],
            ['association.edit', ['association' => $association]],
            ['association.divisions', ['association' => $association]],
            ['association.teams', ['association' => $association]],
            ['association.teams.inactive', ['association' => $association]],
            ['association.venues', ['association' => $association]],
            ['association.venues.inactive', ['association' => $association]],
            ['association.series', ['association' => $association]],
            ['association.users', ['association' => $association]],
            ['association.create', []],
            ['association.deleteConfirm', ['association' => $association]],
            ['association.user.add', ['association' => $association]],
            ['association.user.view', ['association' => $association, 'user' => $targetUser]],
            ['association.user.edit', ['association' => $association, 'user' => $targetUser]],
            ['association.user.token', ['association' => $association, 'user' => $targetUser]],
            ['association.undeleteConfirm', ['association' => $association]],
            ['association.view', ['association' => $association]],
            ['contact_submissions.list', ['association' => $association]],
            ['contact_submission.view', ['association' => $association, 'contactSubmission' => $contactSubmission]],
            ['user', ['user' => $targetUser]],
            ['onboard.association', ['association' => $association]],
            ['onboard.series', ['series' => $series]],
            ['admin.users', []],
            ['admin.associations.deleted', []],
            ['admin', []],
            // Mutating GETs (pre-existing app design, not this test's job to fix) -
            // run last so they don't disturb fixtures the routes above rely on.
            ['association.rulesDelete', ['association' => $association]],
            ['association.homeImageDelete', ['association' => $association]],
        ];

        foreach ($routes as [$name, $params]) {
            $response = $this->get(route($name, $params));

            $this->assertLessThan(
                500,
                $response->status(),
                "Route [$name] returned a {$response->status()} - ".optional($response->exception)->getMessage()
            );
        }
    }

    public function test_api_associations_route_does_not_crash()
    {
        $user = User::factory()->create();
        $token = Str::random(60);
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])->get('/api/associations');

        $this->assertLessThan(500, $response->status());
    }
}
