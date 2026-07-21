<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\Round;
use App\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public /schedule and /schedule/{id}/full pages normally suppress Off
 * Week rounds entirely and show "TBD" for any round with no scheduled
 * matches. A non-empty Round message overrides that: Off Week rounds with a
 * message are shown (title + message instead of a match table), and
 * Playoffs Week rounds with a message show the message instead of "TBD".
 */
class ScheduleFrontendRoundDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(string $subdomain): array
    {
        $association = Association::factory()->create(['subdomain' => $subdomain]);

        $division = new Division(['name' => 'Frontend Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Frontend Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Frontend Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
        ]);

        return compact('association', 'schedule', 'series', 'division');
    }

    private function createRound($schedule, $series, $division, array $attributes): Round
    {
        $round = new Round(array_merge(['name' => 'Round 1'], $attributes));
        $round->schedule_id = $schedule->id;
        $round->series_id = $series->id;
        $round->division_id = $division->id;
        $round->start_date = $attributes['start_date'] ?? now();
        $round->end_date = $attributes['start_date'] ?? now();
        $round->save();

        return $round;
    }

    public function test_off_week_round_with_message_is_shown_on_schedule()
    {
        ['schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('offweek-a');

        $this->createRound($schedule, $series, $division, [
            'name' => 'Round 1', 'off_week' => true, 'message' => 'Season party at the arcade!',
        ]);

        $response = $this->get('http://offweek-a.pinballleague.org/schedule');

        $response->assertStatus(200);
        $response->assertSee('Round 1', false);
        $response->assertSee('Season party at the arcade!', false);
    }

    public function test_off_week_round_without_message_is_suppressed_on_schedule()
    {
        ['schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('offweek-b');

        $this->createRound($schedule, $series, $division, [
            'name' => 'Off Week Round', 'off_week' => true,
        ]);

        $response = $this->get('http://offweek-b.pinballleague.org/schedule');

        $response->assertStatus(200);
        $response->assertDontSee('Off Week Round', false);
    }

    public function test_playoffs_round_with_message_shows_message_instead_of_tbd_on_schedule()
    {
        ['schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('playoffs-a');

        $this->createRound($schedule, $series, $division, [
            'name' => 'Semifinals', 'playoffs_week' => true, 'message' => 'Bracket TBD after Round 4.',
        ]);

        $response = $this->get('http://playoffs-a.pinballleague.org/schedule');

        $response->assertStatus(200);
        $response->assertSee('Semifinals', false);
        $response->assertSee('Bracket TBD after Round 4.', false);
        $response->assertDontSee('TBD</h', false);
    }

    public function test_playoffs_round_without_message_still_shows_tbd_on_schedule()
    {
        ['schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('playoffs-b');

        $this->createRound($schedule, $series, $division, [
            'name' => 'Finals', 'playoffs_week' => true,
        ]);

        $response = $this->get('http://playoffs-b.pinballleague.org/schedule');

        $response->assertStatus(200);
        $response->assertSee('Finals', false);
        $response->assertSee('TBD', false);
    }

    public function test_off_week_round_with_message_is_shown_on_full_schedule()
    {
        ['schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('offweek-full-a');

        $this->createRound($schedule, $series, $division, [
            'name' => 'Round 1', 'off_week' => true, 'message' => 'Season party at the arcade!',
        ]);

        $response = $this->get('http://offweek-full-a.pinballleague.org/schedule/'.$schedule->id.'/full');

        $response->assertStatus(200);
        $response->assertSee('Round 1', false);
        $response->assertSee('Season party at the arcade!', false);
    }

    public function test_playoffs_round_with_message_shows_message_instead_of_tbd_on_full_schedule()
    {
        ['schedule' => $schedule, 'series' => $series, 'division' => $division] = $this->buildFixture('playoffs-full-a');

        $this->createRound($schedule, $series, $division, [
            'name' => 'Semifinals', 'playoffs_week' => true, 'message' => 'Bracket TBD after Round 4.',
        ]);

        $response = $this->get('http://playoffs-full-a.pinballleague.org/schedule/'.$schedule->id.'/full');

        $response->assertStatus(200);
        $response->assertSee('Bracket TBD after Round 4.', false);
    }
}
