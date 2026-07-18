<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Series;
use App\Team;
use App\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreSubmissionArchivedScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function makeMatch(Association $association, Division $division, string $label, bool $archived): PLMatch
    {
        $venue = Venue::create(['name' => "{$label} Venue", 'association_id' => $association->id]);
        $homeTeam = Team::create(['name' => "{$label} Home", 'association_id' => $association->id, 'venue_id' => $venue->id]);
        $awayTeam = Team::create(['name' => "{$label} Away", 'association_id' => $association->id, 'venue_id' => $venue->id]);

        $series = Series::create(['name' => "{$label} Series", 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => "{$label} Schedule", 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(), 'archived' => $archived,
        ]);

        // Kept a day inside the (-1 week .. today) window rather than exactly
        // on the "today" boundary: step2's date-window query does a 'Y-m-d'
        // string comparison against the full timestamp, and on SQLite (this
        // test's driver) that comparison only misbehaves at exact boundary
        // equality - a date strictly inside the window sorts correctly either way.
        $round = new Round(['name' => "{$label} Round"]);
        $round->schedule_id = $schedule->id;
        $round->series_id = $series->id;
        $round->division_id = $division->id;
        $round->start_date = now()->subDays(2)->startOfDay();
        $round->end_date = now()->subDays(2)->startOfDay();
        $round->save();

        $match = new PLMatch(['name' => "{$label} Match"]);
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

        return $match;
    }

    public function test_matches_on_an_archived_schedule_are_excluded_from_score_submission()
    {
        $association = Association::factory()->create(['subdomain' => 'submit-archived']);

        $division = new Division(['name' => 'Submit Division']);
        $division->association_id = $association->id;
        $division->save();

        $activeMatch = $this->makeMatch($association, $division, 'Active', false);
        $archivedMatch = $this->makeMatch($association, $division, 'Archived', true);

        $response = $this->post('http://submit-archived.pinballleague.org/submit/step2', [
            'division_id' => $division->id,
        ]);

        $response->assertStatus(200);
        $response->assertSee('Active Home');
        $response->assertSee('Active Away');
        $response->assertDontSee('Archived Home');
        $response->assertDontSee('Archived Away');
    }
}
