<?php

namespace Tests\Feature;

use App\Association;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveRoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_rounds_excludes_rounds_whose_schedule_is_archived()
    {
        $association = Association::factory()->create();

        $activeSchedule = $association->schedules()->create([
            'name' => 'Active Schedule', 'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(),
        ]);
        $archivedSchedule = $association->schedules()->create([
            'name' => 'Archived Schedule', 'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(), 'archived' => 1,
        ]);

        $activeRound = $activeSchedule->rounds()->create([
            'name' => 'Round on active schedule', 'start_date' => now(), 'end_date' => now(),
        ]);
        $archivedRound = $archivedSchedule->rounds()->create([
            'name' => 'Round on archived schedule', 'start_date' => now(), 'end_date' => now(),
        ]);

        $activeRoundIds = $association->activeRounds->pluck('id');

        $this->assertTrue($activeRoundIds->contains($activeRound->id));
        $this->assertFalse($activeRoundIds->contains($archivedRound->id));
    }
}
