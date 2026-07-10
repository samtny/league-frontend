<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PinballMapClient
{
    /**
     * Fetches the machines installed at a Pinball Map location, cached for
     * services.pinballmap.cache_ttl_seconds. Failures (bad id, timeout, API
     * outage) are logged and cached as an empty result for the same TTL
     * rather than retried on every page load - this keeps a single broken
     * venue from hammering the Pinball Map API or slowing down the page.
     */
    public function machinesForLocation(?string $pinballmapId): array
    {
        if (empty($pinballmapId)) {
            return [];
        }

        return Cache::remember(
            "pinballmap.machines.{$pinballmapId}",
            config('services.pinballmap.cache_ttl_seconds'),
            function () use ($pinballmapId) {
                try {
                    $response = Http::timeout(5)->get(
                        config('services.pinballmap.base_url')."/locations/{$pinballmapId}/machine_details.json"
                    );

                    if (! $response->successful()) {
                        Log::warning("Pinball Map lookup failed for location {$pinballmapId}", ['status' => $response->status()]);

                        return [];
                    }

                    return $response->json('machines', []);
                } catch (\Throwable $e) {
                    Log::warning("Pinball Map lookup errored for location {$pinballmapId}: {$e->getMessage()}");

                    return [];
                }
            }
        );
    }
}
