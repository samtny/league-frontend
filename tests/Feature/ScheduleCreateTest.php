<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\Round;
use App\Schedule;
use App\Series;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Create Schedule form used to have its own "Generate Schedule"
 * select box (Manual/Automatic Random) separate from the Generate Matches
 * wizard. It's been removed for parity with the Edit Schedule form, which
 * has never had one: ScheduleController::store() now generates Rounds the
 * same way ScheduleController::update() does - automatically, whenever
 * start/end date and weekday are all present - rather than reading a
 * "generate" field from the request.
 */
class ScheduleCreateTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(string $subdomain): array
    {
        $association = Association::factory()->create(['subdomain' => $subdomain]);

        $division = new Division(['name' => 'Create Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Create Series', 'association_id' => $association->id]);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        return compact('association', 'series', 'division');
    }

    public function test_create_form_no_longer_shows_a_generate_schedule_select()
    {
        ['association' => $association, 'series' => $series] = $this->buildFixture('create-a');

        $response = $this->get(route('schedule.create', ['association' => $association, 'series' => $series]));

        $response->assertStatus(200);
        $response->assertDontSee('Generate Schedule');
        $response->assertDontSee('Manual Assignment (Empty Rounds)');
        $response->assertDontSee('Automatic Random Assignment');
    }

    public function test_creating_with_full_date_range_and_weekday_generates_rounds_automatically()
    {
        ['association' => $association, 'series' => $series, 'division' => $division] = $this->buildFixture('create-b');

        $response = $this->post(route('schedule.create', ['association' => $association, 'series' => $series]), [
            'name' => 'New Schedule',
            'division_id' => $division->id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-20',
            'weekday' => 'mon',
        ]);

        $schedule = Schedule::where('name', 'New Schedule')->firstOrFail();

        $response->assertRedirect(route('series.schedules', ['association' => $association, 'series' => $series]));

        // Mondays 07-06, 07-13, 07-20.
        $this->assertSame(3, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_creating_without_a_weekday_creates_no_rounds()
    {
        ['association' => $association, 'series' => $series, 'division' => $division] = $this->buildFixture('create-c');

        $response = $this->post(route('schedule.create', ['association' => $association, 'series' => $series]), [
            'name' => 'New Schedule',
            'division_id' => $division->id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-20',
        ]);

        $schedule = Schedule::where('name', 'New Schedule')->firstOrFail();

        $response->assertRedirect(route('series.schedules', ['association' => $association, 'series' => $series]));
        $this->assertSame(0, Round::where('schedule_id', $schedule->id)->count());
    }
}
