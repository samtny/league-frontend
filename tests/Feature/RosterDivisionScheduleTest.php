<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterDivisionScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_only_shows_teams_whose_division_has_a_non_archived_schedule()
    {
        $association = Association::factory()->create(['subdomain' => 'roster-divisions']);

        $divisionWithActiveSchedule = new Division(['name' => 'Active Division']);
        $divisionWithActiveSchedule->association_id = $association->id;
        $divisionWithActiveSchedule->save();
        $association->schedules()->create([
            'name' => 'Active Schedule', 'division_id' => $divisionWithActiveSchedule->id,
            'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(),
        ]);

        $divisionWithArchivedSchedule = new Division(['name' => 'Archived Division']);
        $divisionWithArchivedSchedule->association_id = $association->id;
        $divisionWithArchivedSchedule->save();
        $association->schedules()->create([
            'name' => 'Archived Schedule', 'division_id' => $divisionWithArchivedSchedule->id,
            'start_date' => now()->subMonth(), 'end_date' => now()->subWeek(), 'archived' => 1,
        ]);

        $divisionWithNoSchedule = new Division(['name' => 'Scheduleless Division']);
        $divisionWithNoSchedule->association_id = $association->id;
        $divisionWithNoSchedule->save();

        Team::create(['name' => 'Team Active Division', 'association_id' => $association->id, 'active' => true, 'division_id' => $divisionWithActiveSchedule->id]);
        Team::create(['name' => 'Team Archived Division', 'association_id' => $association->id, 'active' => true, 'division_id' => $divisionWithArchivedSchedule->id]);
        Team::create(['name' => 'Team Scheduleless Division', 'association_id' => $association->id, 'active' => true, 'division_id' => $divisionWithNoSchedule->id]);
        Team::create(['name' => 'Team No Division', 'association_id' => $association->id, 'active' => true]);

        $response = $this->get('http://roster-divisions.pinballleague.org/roster');

        $response->assertStatus(200);
        $response->assertSee('Team Active Division');
        $response->assertDontSee('Team Archived Division');
        $response->assertDontSee('Team Scheduleless Division');
        $response->assertDontSee('Team No Division');
    }
}
