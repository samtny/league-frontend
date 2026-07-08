<?php

namespace Tests;

use Bouncer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Bouncer's ability cache lives in a process-wide in-memory store, not
     * the DB - it survives RefreshDatabase transactions and outlives any
     * single test. Bouncer::refresh() only re-clears entries for authorities
     * that still exist, so it can't reach a stale entry for a user a prior
     * test already deleted - and SQLite happily reuses that user's id in
     * the next test, inheriting the wrong cached answer. Disabling the
     * cache for the whole suite (Bouncer's own recommendation for tests)
     * avoids this entirely.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Bouncer::dontCache();
    }
}
