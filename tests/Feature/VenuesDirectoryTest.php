<?php

namespace Tests\Feature;

use App\Association;
use App\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VenuesDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_venues_are_listed_ordered_by_name()
    {
        $association = Association::factory()->create(['subdomain' => 'venues-active']);

        Venue::create(['name' => 'Zebra Lanes', 'association_id' => $association->id, 'active' => true]);
        Venue::create(['name' => 'Alpha Arcade', 'association_id' => $association->id, 'active' => true]);
        Venue::create(['name' => 'Hidden Hideout', 'association_id' => $association->id, 'active' => false]);

        $response = $this->get('http://venues-active.pinballleague.org/venues');

        $response->assertStatus(200);
        $response->assertSeeInOrder(['Alpha Arcade', 'Zebra Lanes']);
        $response->assertDontSee('Hidden Hideout');
    }

    public function test_venue_with_pinballmap_id_shows_games_from_the_api()
    {
        Http::fake([
            'pinballmap.com/*' => Http::response(['machines' => [
                ['id' => 1, 'name' => 'Godzilla (Pro)'],
                ['id' => 2, 'name' => 'JAWS (Premium)'],
            ]]),
        ]);

        $association = Association::factory()->create(['subdomain' => 'venues-games']);
        Venue::create(['name' => 'Arcade One', 'association_id' => $association->id, 'active' => true, 'pinballmap_id' => '874']);

        $response = $this->get('http://venues-games.pinballleague.org/venues');

        $response->assertStatus(200);
        $response->assertSee('Godzilla (Pro)');
        $response->assertSee('JAWS (Premium)');
    }

    public function test_venue_without_pinballmap_id_shows_empty_state_and_makes_no_request()
    {
        Http::fake();

        $association = Association::factory()->create(['subdomain' => 'venues-no-id']);
        Venue::create(['name' => 'Arcade Two', 'association_id' => $association->id, 'active' => true]);

        $response = $this->get('http://venues-no-id.pinballleague.org/venues');

        $response->assertStatus(200);
        $response->assertSee('No games listed yet.');
        Http::assertNothingSent();
    }

    public function test_failed_pinballmap_lookup_fails_soft()
    {
        Http::fake([
            'pinballmap.com/*' => Http::response(null, 500),
        ]);

        $association = Association::factory()->create(['subdomain' => 'venues-failing']);
        Venue::create(['name' => 'Arcade Three', 'association_id' => $association->id, 'active' => true, 'pinballmap_id' => '999999']);

        $response = $this->get('http://venues-failing.pinballleague.org/venues');

        $response->assertStatus(200);
        $response->assertSee('Arcade Three');
        $response->assertSee('No games listed yet.');
    }

    public function test_repeated_requests_within_ttl_only_hit_the_api_once()
    {
        Http::fake([
            'pinballmap.com/*' => Http::response(['machines' => [['id' => 1, 'name' => 'Godzilla (Pro)']]]),
        ]);

        $association = Association::factory()->create(['subdomain' => 'venues-cached']);
        Venue::create(['name' => 'Arcade Four', 'association_id' => $association->id, 'active' => true, 'pinballmap_id' => '874']);

        $this->get('http://venues-cached.pinballleague.org/venues')->assertStatus(200);
        $this->get('http://venues-cached.pinballleague.org/venues')->assertStatus(200);

        Http::assertSentCount(1);
    }
}
