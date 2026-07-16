<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\Round;
use App\Series;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_round_redirects_to_the_schedule_page()
    {
        $association = Association::factory()->create(['subdomain' => 'round-update-a']);

        $division = new Division(['name' => 'Round Update Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Round Update Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Round Update Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
        ]);

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $series->id;
        $round->division_id = $division->id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        $response = $this->post(route('round.update', ['association' => $association, 'schedule' => $schedule, 'round' => $round]), [
            'name' => 'Round 1 Updated',
            'start_date' => $round->start_date,
            'end_date' => $round->end_date,
        ]);

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));
        $this->assertSame('Round 1 Updated', $round->fresh()->name);
    }
}
