<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Series;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Generate Rounds" flow used to be a select box embedded in the
 * Schedule edit form. It's now a standalone wizard reachable from
 * /admin/association/{a}/schedule/{s}: step 1 asks for confirmation before
 * deleting any existing rounds (skipped entirely if there are none), step 2
 * picks an assignment method (Manual/Automatic radios) and generates rounds
 * using the Schedule's own persisted start_date/end_date/weekday.
 */
class GenerateRoundsWizardTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(string $subdomain, ?string $weekday = 'mon'): array
    {
        $association = Association::factory()->create(['subdomain' => $subdomain]);

        $division = new Division(['name' => 'Wizard Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Wizard Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Wizard Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'weekday' => $weekday,
        ]);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        return compact('association', 'schedule');
    }

    public function test_no_existing_rounds_goes_straight_to_the_select_step()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-a');

        $response = $this->get(route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Manual Assignment (Empty Rounds)');
        $response->assertDontSee('confirm you want to delete');
    }

    public function test_existing_rounds_show_the_confirm_delete_step()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-b');

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $response = $this->get(route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('confirm you want to delete');
        $response->assertDontSee('Manual Assignment (Empty Rounds)');
    }

    public function test_confirm_delete_removes_rounds_and_advances_to_select_step()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-c');

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $response = $this->post(route('schedule.generate-rounds.delete', ['association' => $association, 'schedule' => $schedule]));

        $response->assertRedirect(route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]));
        $this->assertDatabaseMissing('rounds', ['id' => $round->id]);

        $followUp = $this->get(route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]));
        $followUp->assertSee('Manual Assignment (Empty Rounds)');
    }

    public function test_manual_assignment_creates_rounds_from_the_schedules_own_weekday()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-d', 'mon');

        $response = $this->post(route('schedule.generate-rounds.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'manual',
        ]);

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        // July 2026 has 4 Mondays (6th, 13th, 20th, 27th).
        $this->assertSame(4, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_selecting_automatic_assignment_clears_old_rounds_and_goes_to_review()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-e');

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $response = $this->post(route('schedule.generate-rounds.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);

        // Nothing is persisted until the review screen's Accept is submitted,
        // but any pre-existing rounds are cleared immediately regardless.
        $response->assertRedirect(route('schedule.generate-rounds.review', ['association' => $association, 'schedule' => $schedule]));
        $this->assertSame(0, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_generate_requires_a_valid_method()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-f');

        $response = $this->post(route('schedule.generate-rounds.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'bogus',
        ]);

        $response->assertSessionHasErrors('generate');
        $this->assertSame(0, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_editing_schedule_persists_weekday_but_no_longer_generates_rounds()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-g', null);

        $response = $this->post(route('schedule.update', ['association' => $association, 'schedule' => $schedule]), [
            'name' => 'Wizard Schedule',
            'division_id' => $schedule->division_id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'weekday' => 'wed',
        ]);

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'weekday' => 'wed']);
        $this->assertSame(0, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_missing_weekday_shows_invalid_state_instead_of_confirm_or_select_step()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-h', null);

        $response = $this->get(route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Match Weekday');
        $response->assertDontSee('confirm you want to delete');
        $response->assertDontSee('Manual Assignment (Empty Rounds)');
    }

    public function test_missing_weekday_blocks_deleting_existing_rounds()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-i', null);

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $response = $this->post(route('schedule.generate-rounds.delete', ['association' => $association, 'schedule' => $schedule]));

        $response->assertRedirect(route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]));
        $this->assertDatabaseHas('rounds', ['id' => $round->id]);
    }

    public function test_missing_weekday_blocks_generating_even_if_posted_directly()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-j', null);

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $response = $this->post(route('schedule.generate-rounds.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'manual',
        ]);

        $response->assertRedirect(route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]));
        $this->assertDatabaseHas('rounds', ['id' => $round->id]);
    }

    public function test_missing_start_or_end_date_also_blocks_generation()
    {
        $association = Association::factory()->create(['subdomain' => 'wizard-k']);

        $division = new Division(['name' => 'Wizard Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Wizard Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Wizard Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => null, 'end_date' => null, 'weekday' => 'mon',
        ]);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        $response = $this->get(route('schedule.generate-rounds', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Start Date');
        $response->assertSee('End Date');
        $response->assertDontSee('Manual Assignment (Empty Rounds)');
    }
}
