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

    public function test_page_attributes_game_data_to_pinball_map()
    {
        $association = Association::factory()->create(['subdomain' => 'venues-attribution']);

        $response = $this->get('http://venues-attribution.pinballleague.org/venues');

        $response->assertStatus(200);
        $response->assertSee('Game data courtesy of');
        $response->assertSee('href="https://pinballmap.com" target="_blank" rel="noopener noreferrer"', false);
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

    public function test_game_reference_links_reflect_whichever_ids_are_present()
    {
        Http::fake([
            'pinballmap.com/*' => Http::response(['machines' => [
                ['id' => 1, 'name' => 'Godzilla (Pro)', 'ipdb_id' => 6841, 'opdb_id' => 'GweeP-MW95j'],
                ['id' => 2, 'name' => 'Dungeons & Dragons (Pro)', 'ipdb_id' => null, 'opdb_id' => 'GK1Ej-MwNZr'],
                ['id' => 3, 'name' => 'Mystery Machine', 'ipdb_id' => null, 'opdb_id' => null],
            ]]),
        ]);

        $association = Association::factory()->create(['subdomain' => 'venues-refs']);
        Venue::create(['name' => 'Arcade Five', 'association_id' => $association->id, 'active' => true, 'pinballmap_id' => '874']);

        $response = $this->get('http://venues-refs.pinballleague.org/venues');

        $response->assertStatus(200);
        // The view only ever links via opdb_id (Matchplay); ipdb_id is not
        // currently used to build a link, even when present alongside opdb_id.
        $response->assertSee('https://app.matchplay.events/opdb/entries/GweeP-MW95j/pintips', false);
        $response->assertSee('https://app.matchplay.events/opdb/entries/GK1Ej-MwNZr/pintips', false);
        $response->assertDontSee('https://www.ipdb.org/machine.cgi?id=6841', false);

        $html = $response->getContent();
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);

        // Mystery Machine has neither id, so it renders as plain text with no link.
        $mysteryPos = strpos($html, 'Mystery Machine');
        $this->assertNotFalse($mysteryPos);
        $precedingChunk = substr($html, max(0, $mysteryPos - 80), 80);
        $this->assertStringNotContainsString('<a href', $precedingChunk);
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
