@extends('layouts.admin')

@section('title', 'Create Schedule')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.create', $series) }}
@endsection

@section('content')
    <h1>
        Create Schedule
    </h1>
    <div class="form">
        <form method="POST" action="{{ route('schedule.create', ['association' => $association, 'series' => $series ]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Schedule, like <em>"A Division"</em></small>
            </div>

            <div class="mb-3">
                <label for="division_id">Division</label>
                <select class="form-control @error('division_id') is-invalid @enderror" id="division_id" name="division_id" required>
                    <option value="" disabled <?php echo old('division_id') ? '' : 'selected'; ?>>- Select a division -</option>
                    <?php foreach($available_divisions as $item): ?>
                        <option value="<?php echo $item->id; ?>"<?php if (old('division_id') == $item->id) echo ' selected'; ?>><?php echo $item->name; ?></option>
                    <?php endforeach; ?>
                </select>
                @error('division_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="start_date">Start Date</label>
                <input class="form-control @error('start_date') is-invalid @enderror" id="start_date" type="date" name="start_date" value="{{ old('start_date') }}" required>
                @error('start_date')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="end_date">End Date</label>
                <input class="form-control @error('end_date') is-invalid @enderror" id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" required>
                @error('end_date')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label>Active Venues</label>
                <?php $selectedDivisionId = old('division_id'); ?>
                <?php $venueEligible = function ($venue) use ($selectedDivisionId) {
                    return empty($selectedDivisionId) || $venue->divisions->contains('id', $selectedDivisionId);
                }; ?>
                <?php $visibleVenues = $association->venues->filter(function ($venue) use ($venueEligible) {
                    return $venue->active && $venueEligible($venue);
                })->sortBy('name'); ?>
                <?php $selectedVenueIds = old('venue_ids', $visibleVenues->pluck('id')->toArray()); ?>
                <?php if (!$visibleVenues->isEmpty()): ?>
                    <?php foreach ($visibleVenues as $venue): ?>
                        <div class="form-check">
                            <input id="venue_<?php echo $venue->id; ?>" type="checkbox" class="form-check-input" name="venue_ids[]" value="<?php echo $venue->id; ?>" <?php if (in_array($venue->id, $selectedVenueIds)) echo 'checked'; ?>>
                            <label for="venue_<?php echo $venue->id; ?>" class="form-check-label"><?php echo $venue->name; ?></label>
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

            <div class="mb-3">
                <legend>Match Weekday</legend>
                <fieldset>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_sunday" name="weekday" value="sun" required <?php if (old('weekday') == 'sun') echo 'checked'; ?>>
                        <label class="form-check-label" for="weekday_sunday">Sunday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_monday" name="weekday" value="mon" required <?php if (old('weekday') == 'mon') echo 'checked'; ?>>
                        <label class="form-check-label" for="weekday_monday">Monday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_tuesday" name="weekday" value="tue" required <?php if (old('weekday') == 'tue') echo 'checked'; ?>>
                        <label class="form-check-label" for="weekday_tuesday">Tuesday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_wednesday" name="weekday" value="wed" required <?php if (old('weekday') == 'wed') echo 'checked'; ?>>
                        <label class="form-check-label" for="weekday_wednesday">Wednesday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_thursday" name="weekday" value="thu" required <?php if (old('weekday') == 'thu') echo 'checked'; ?>>
                        <label class="form-check-label" for="weekday_thursday">Thursday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_friday" name="weekday" value="fri" required <?php if (old('weekday') == 'fri') echo 'checked'; ?>>
                        <label class="form-check-label" for="weekday_friday">Friday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_saturday" name="weekday" value="sat" required <?php if (old('weekday') == 'sat') echo 'checked'; ?>>
                        <label class="form-check-label" for="weekday_saturday">Saturday</label>
                    </div>
                </fieldset>
                @error('weekday')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" name="archived" type="checkbox" value="1" id="archived" <?php echo old('archived') ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="archived">
                        Archived
                    </label>
                    <small class="form-text text-muted">When checked, this schedule will not show in the Standings / Results pages.</small>
                </div>
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Create"/>
                </div>
            </div>

        </form>
    </div>
@endsection
