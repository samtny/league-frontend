<?php

namespace App\Services\ScheduleGeneration;

/**
 * Thrown when the greedy pairing for a round paints itself into a corner
 * (the last team(s) left can only be paired with their preceding-round
 * opponent). Caught by ScheduleGenerator, which discards the whole attempt
 * and retries with a fresh shuffle rather than trying to backtrack.
 */
final class UnableToBuildRoundException extends \RuntimeException
{
}
