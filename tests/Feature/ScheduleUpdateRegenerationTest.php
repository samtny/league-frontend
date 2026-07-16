<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\Round;
use App\Series;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing a Schedule's weekday/start/end date can leave its existing Rounds
 * stale (wrong dates relative to the new values). ScheduleController::update()
 * detects that mismatch and, if Rounds exist, defers to a confirmation step
 * before deleting/regenerating them.
 */
class ScheduleUpdateRegenerationTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(string $subdomain): array
    {
        $association = Association::factory()->create(['subdomain' => $subdomain]);

        $division = new Division(['name' => 'Update Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Update Series', 'association_id' => $association->id]);

        $schedule = $association->schedules()->create([
            'name' => 'Update Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => '2026-07-06', 'end_date' => '2026-07-20', 'weekday' => 'mon',
        ]);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        return compact('association', 'schedule', 'series', 'division');
    }

    private function createRoundOn($schedule, $series, $division, string $date): Round
    {
        $round = new Round(['name' => 'Round']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $series->id;
        $round->division_id = $division->id;
        $round->start_date = $date;
        $round->end_date = $date;
        $round->save();

        return $round;
    }

    public function test_updating_with_no_rounds_generates_rounds_automatically()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('update-a');

        $response = $this->post(route('schedule.update', ['association' => $association, 'schedule' => $schedule]), [
            'name' => 'Update Schedule',
            'division_id' => $schedule->division_id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-20',
            'weekday' => 'mon',
        ]);

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        // Mondays 07-06, 07-13, 07-20.
        $this->assertSame(3, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_updating_with_matching_rounds_saves_without_confirmation()
    {
        ['association' => $association, 'schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('update-b');

        foreach (['2026-07-06', '2026-07-13', '2026-07-20'] as $date) {
            $this->createRoundOn($schedule, $series, $division, $date);
        }

        $response = $this->post(route('schedule.update', ['association' => $association, 'schedule' => $schedule]), [
            'name' => 'Renamed Schedule',
            'division_id' => $schedule->division_id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-20',
            'weekday' => 'mon',
        ]);

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        $this->assertSame(3, Round::where('schedule_id', $schedule->id)->count());
        $this->assertSame('Renamed Schedule', $schedule->fresh()->name);
    }

    public function test_updating_with_mismatched_rounds_requires_confirmation_before_saving()
    {
        ['association' => $association, 'schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('update-c');

        foreach (['2026-07-06', '2026-07-13', '2026-07-20'] as $date) {
            $this->createRoundOn($schedule, $series, $division, $date);
        }

        $response = $this->post(route('schedule.update', ['association' => $association, 'schedule' => $schedule]), [
            'name' => 'Update Schedule',
            'division_id' => $schedule->division_id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-27',
            'weekday' => 'mon',
        ]);

        $response->assertRedirect(route('schedule.update.confirm', ['association' => $association, 'schedule' => $schedule]));

        // Nothing persisted yet.
        $this->assertStringStartsWith('2026-07-20', $schedule->fresh()->end_date);
        $this->assertSame(3, Round::where('schedule_id', $schedule->id)->count());

        $confirmPage = $this->get(route('schedule.update.confirm', ['association' => $association, 'schedule' => $schedule]));
        $confirmPage->assertStatus(200);

        $accept = $this->post(route('schedule.update.confirm.accept', ['association' => $association, 'schedule' => $schedule]));

        $accept->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        $schedule->refresh();
        $this->assertStringStartsWith('2026-07-27', $schedule->end_date);

        // Mondays 07-06, 07-13, 07-20, 07-27.
        $this->assertSame(4, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_visiting_confirm_page_without_a_pending_edit_redirects_to_edit()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('update-d');

        $response = $this->get(route('schedule.update.confirm', ['association' => $association, 'schedule' => $schedule]));

        $response->assertRedirect(route('schedule.edit', ['association' => $association, 'schedule' => $schedule]));
    }
}
