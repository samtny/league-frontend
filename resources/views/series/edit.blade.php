@extends('layouts.app')

@section('title', 'Create Series')

@section('content')
    <div class="title m-b-md">
        Edit Series
    </div>
    <div class="form">
        <form method="POST" action="/series/update">
            @csrf

            <div class="form-item">
                <label for="user_id">Owner</label>
                <select id="user_id" name="user_id">
                    <option value="<?php echo($current_user->id); ?>"><?php echo($current_user->name); ?></option>
                </select>
            </div>

            <input type="hidden" name="id" value="{{ $series->id }}">

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ $series->name }}" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <span class="form-item-help">Enter a name for this series, like <em>"Summer 2019"</em> or whatever you would like.</span>
            </div>

            <div class="form-item">
                <label for="start_date">Start Date</label>
                <input id="start_date" type="date" name="start_date" value="{{ $start_date_string }}">
            </div>

            <div class="form-item">
                <label for="end_date">End Date</label>
                <input id="end_date" type="date" name="end_date" value="{{ $end_date_string }}">
            </div>

            <div class="form-item">
                <label for="association_id">Association</label>
                <select id="association_id" name="association_id">
                    <option value="">- No Association -</option>
                    <?php foreach($associations as $association): ?>
                <option value="<?php echo $association->id; ?>"<?php if ($association_id == $association->id): ?> selected<?php endif; ?>><?php echo $association->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($rounds)): ?>
                <?php foreach ($rounds as $index => $round): ?>
                    <a href="/round/<?php echo($round->id); ?>">
                        <?php echo ('<div class="round">' . $round->name . '</div>'); ?> — <?php echo date('d-m-Y', strtotime($round->start_date)); ?>
                    </a>
                    <a href="/round/<?php echo($round->id); ?>/edit">
                        Edit
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="message">
                    No rounds.
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
    <div class="links">
        <a href="{{ route('schedule.create', [ 'series' => $series ]) }}">Create Schedule</a>
        <a href="{{ route('schedule.edit', [ 'series' => $series ]) }}">Edit Schedule</a>
        <a href="{{ route('series.delete', [ 'series' => $series ]) }}">Delete Series</a>
    </div>
@endsection
