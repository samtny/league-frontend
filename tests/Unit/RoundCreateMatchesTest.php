<?php

namespace Tests\Unit;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Schedule;
use App\Series;
use App\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundCreateMatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_matches_only_creates_matches_for_active_venues()
    {
        $association = Association::factory()->create();

        $activeVenue = Venue::create(['name' => 'Active Venue', 'association_id' => $association->id, 'active' => true]);
        $inactiveVenue = Venue::create(['name' => 'Inactive Venue', 'association_id' => $association->id, 'active' => false]);

        $division = new Division(['name' => 'Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'weekday' => 'mon',
        ]);

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $round->createMatches();

        $this->assertSame(1, PLMatch::where('round_id', $round->id)->count());
        $this->assertDatabaseHas('matches', ['round_id' => $round->id, 'venue_id' => $activeVenue->id]);
        $this->assertDatabaseMissing('matches', ['round_id' => $round->id, 'venue_id' => $inactiveVenue->id]);
    }
}
