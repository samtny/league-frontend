@extends('layouts.admin')

@section('title', 'Edit Series')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="title m-b-md h1">
                Edit Series – <?php echo $series->name; ?>
            </div>
        </div>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('series.update', ['series' => $series ]) }}">
            @csrf

            <div class="form-group">
                <label for="user_id">Owner</label>
                <select id="user_id" name="user_id">
                    <option value="<?php echo($current_user->id); ?>"><?php echo($current_user->name); ?></option>
                </select>
            </div>

            <?php if (!empty($series->association)): ?>
            <div class="form-group">
                <label for="association">Association</label>
                <input type="text" readonly value="<?php echo $series->association->name; ?>">
            </div>
            <?php endif; ?>

            <input type="hidden" name="id" value="{{ $series->id }}">

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ $series->name }}" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input id="start_date" type="date" name="start_date" value="{{ $start_date_string }}">
            </div>

            <div class="form-group">
                <label for="end_date">End Date</label>
                <input id="end_date" type="date" name="end_date" value="{{ $end_date_string }}">
            </div>

            Schedules
            <?php if (!empty($schedules)): ?>
                <?php foreach ($schedules as $index => $item): ?>
                    <a href="{{ route('schedule', ['schedule' => $item ])}}">
                        <?php echo ('<div class="schedule">' . $item->name . '</div>'); ?> — <?php echo date('Y-m-d', strtotime($item->start_date)); ?>
                    </a>
                    <a href="{{ route('schedule.edit', ['schedule' => $item ])}}">
                        Edit
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="message">
                    No schedules.
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
    <div class="links">
        <a href="{{ route('schedule.create', [ 'series' => $series ]) }}">Create Schedule</a>
        <a href="{{ route('series.delete', [ 'series' => $series ]) }}">Delete Series</a>
    </div>
@endsection
