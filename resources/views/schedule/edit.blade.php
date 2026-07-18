@extends('layouts.admin')

@section('title', 'Edit Schedule')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.edit', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Edit Schedule</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('schedule.update', ['association' => $association, 'schedule' => $schedule]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $schedule->name) }}" required>
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Schedule, like <em>"A Division"</em>, <em>"Left Ramp"</em>, etc.</small>
            </div>

            <div class="mb-3">
                <label for="division_id">Division</label>
                <select class="form-control" id="division_id" name="division_id">
                    <option value="">- No division -</option>
                    <?php foreach($association->divisions as $division): ?>
                        <option value="<?php echo $division->id; ?>"<?php echo $division->id == old('division_id', $schedule->division_id) ? ' selected' : ''; ?>><?php echo $division->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="start_date">Start Date</label>
                <input id="start_date" class="form-control" type="date" name="start_date" value="<?php echo $schedule->start_date != null ? date('Y-m-d', strtotime($schedule->start_date)) : null ?>">
                <small class="form-text text-muted">Date of the first match for this season (do not include season opener party, etc.)</small>
            </div>

            <div class="mb-3">
                <label for="end_date">End Date</label>
                <input id="end_date" class="form-control" type="date" name="end_date" value="<?php echo $schedule->end_date != null ? date('Y-m-d', strtotime($schedule->end_date)) : null ?>">
                <small class="form-text text-muted">Date of the Finals (last) match for this season.</small>
            </div>

            <div class="mb-3">
                <label>Active Venues</label>
                <?php $venueEligible = function ($venue) use ($schedule) {
                    return $schedule->division_id === null || $venue->divisions->contains('id', $schedule->division_id);
                }; ?>
                <?php $visibleVenues = $association->venues->filter(function ($venue) use ($schedule, $venueEligible) {
                    $alreadyLinked = $venue->schedule_id == $schedule->id;
                    if ($alreadyLinked) {
                        return true;
                    }
                    return $venue->active && $venueEligible($venue);
                })->sortBy('name'); ?>
                <?php $defaultVenueIds = $schedule->venues_configured ? $schedule->venues->pluck('id')->toArray() : $visibleVenues->pluck('id')->toArray(); ?>
                <?php $selectedVenueIds = old('venue_ids', $defaultVenueIds); ?>
                <?php if (!$visibleVenues->isEmpty()): ?>
                    <?php foreach ($visibleVenues as $venue): ?>
                        <?php $venueWrongDivision = $schedule->division_id !== null && !$venueEligible($venue); ?>
                        <div class="form-check<?php if ($venueWrongDivision): ?> text-warning<?php endif; ?>">
                            <input id="venue_<?php echo $venue->id; ?>" type="checkbox" class="form-check-input" name="venue_ids[]" value="<?php echo $venue->id; ?>" <?php if (in_array($venue->id, $selectedVenueIds)) echo 'checked'; ?>>
                            <label for="venue_<?php echo $venue->id; ?>" class="form-check-label"<?php if ($venueWrongDivision): ?> title="This venue is not eligible for the schedule's division."<?php endif; ?>><?php echo $venue->name; ?><?php if (!$venue->active) echo ' (inactive)'; ?><?php if ($venueWrongDivision) echo ' (wrong division)'; ?></label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="message">
                        No venues for this association.
                    </div>
                <?php endif; ?>
                @error('venue_ids')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <?php $weekday_value = old('weekday', $schedule->weekday); ?>
            <div class="mb-3">
                <legend>Match Weekday</legend>
                <fieldset>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_sunday" name="weekday" value="sun" <?php echo $weekday_value === 'sun' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="weekday_sunday">Sunday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_monday" name="weekday" value="mon" <?php echo $weekday_value === 'mon' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="weekday_monday">Monday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_tuesday" name="weekday" value="tue" <?php echo $weekday_value === 'tue' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="weekday_tuesday">Tuesday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_wednesday" name="weekday" value="wed" <?php echo $weekday_value === 'wed' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="weekday_wednesday">Wednesday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_thursday" name="weekday" value="thu" <?php echo $weekday_value === 'thu' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="weekday_thursday">Thursday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_friday" name="weekday" value="fri" <?php echo $weekday_value === 'fri' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="weekday_friday">Friday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_saturday" name="weekday" value="sat" <?php echo $weekday_value === 'sat' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="weekday_saturday">Saturday</label>
                    </div>
                </fieldset>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" name="archived" type="checkbox" value="1" id="archived" <?php echo old('archived', $schedule->archived) ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="archived">
                        Archived
                    </label>
                    <small class="form-text text-muted">When checked, this schedule will not show in the Standings / Results pages.</small>
                </div>
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input id="submit" class="btn btn-primary" type="submit" value="Update"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-warning" href="{{ route('schedule.delete-confirm', ['association' => $association, 'schedule' => $schedule]) }}">Delete Schedule</a>
                </div>
            </div>

        </form>
    </div>
@endsection
