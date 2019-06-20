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
        <form method="POST" action="{{ route('schedule.update', ['schedule' => $schedule]) }}">
            @csrf

            <input type="hidden" name="id" value="<?php echo $schedule->id; ?>">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $schedule->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Schedule, like <em>"A Division"</em></small>
            </div>

            <div class="form-group">
                <label for="division_id">Division</label>
                <select class="form-control" id="division_id" name="division_id">
                    <option value="">- No division -</option>
                    <?php foreach($association->divisions as $division): ?>
                        <option value="<?php echo $division->id; ?>"<?php echo $division->id == old('division_id', $schedule->division_id) ? ' selected' : ''; ?>><?php echo $division->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input id="start_date" class="form-control" type="date" name="start_date" value="<?php echo $schedule->start_date != null ? date('Y-m-d', strtotime($schedule->start_date)) : null ?>">
            </div>

            <div class="form-group">
                <label for="end_date">End Date</label>
                <input id="end_date" class="form-control" type="date" name="end_date" value="<?php echo $schedule->end_date != null ? date('Y-m-d', strtotime($schedule->end_date)) : null ?>">
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" class="btn btn-primary" type="submit" value="Update"/>
                </div>
            </div>

        </form>
    </div>
@endsection
