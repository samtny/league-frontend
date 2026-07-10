<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Strict 200 coverage for every public, unauthenticated page on an
 * association's subdomain site. Unlike RouteSmokeTest (which only asserts
 * "not a 500" across the whole admin surface), these are the pages real
 * anonymous visitors land on, so a plain redirect or error status here is
 * itself a regression worth failing the build over.
 */
class FrontendSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_association_pages_return_200()
    {
        $association = Association::factory()->create(['subdomain' => 'frontend-smoke']);

        $division = new Division(['name' => 'Frontend Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Frontend Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Frontend Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(),
        ]);

        $base = 'http://frontend-smoke.pinballleague.org';

        $paths = [
            '/',
            '/about',
            '/rules',
            '/css/association.css',
            '/standings',
            '/standings/archive',
            '/schedule',
            '/schedule/'.$schedule->id.'/full',
            '/roster',
            '/venues',
            '/contact',
            '/contact/thanks',
            '/submit',
        ];

        foreach ($paths as $path) {
            $response = $this->get($base.$path);

            $this->assertSame(
                200,
                $response->status(),
                "GET $path returned {$response->status()} - ".optional($response->exception)->getMessage()
            );
        }
    }
}
