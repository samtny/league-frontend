@extends('layouts.admin')

@section('title', 'Generate Rounds')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.generate-rounds', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Generate Rounds</h1>
    </div>
    <div class="row mb-3">
        <div class="col">
            <div class="alert alert-danger">
                This Schedule is missing the following before rounds can be generated: {{ implode(', ', $missingFields) }}. Edit the Schedule to set {{ count($missingFields) > 1 ? 'these' : 'this' }} first.
            </div>
        </div>
    </div>
    <div class="form-actions">
        <div class="mb-3">
            <a class="btn btn-primary" href="{{ route('schedule.edit', ['association' => $association, 'schedule' => $schedule]) }}">Edit Schedule</a>
        </div>
        <div class="mb-3">
            <a class="btn btn-secondary" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">Cancel</a>
        </div>
    </div>
@endsection
