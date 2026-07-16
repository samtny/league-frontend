@extends('layouts.admin')

@section('title', 'Generate Matches')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.generate-matches', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Generate Matches</h1>
    </div>
    <div class="row mb-3">
        <div class="col">
            <div class="message">
                Matches for this Schedule already have Home/Away teams assigned. Continuing will clear those assignments if you choose either option on the next step.
            </div>
        </div>
    </div>
    <div class="form-actions">
        <div class="mb-3">
            <a class="btn btn-primary" href="{{ route('schedule.generate-matches.select', ['association' => $association, 'schedule' => $schedule]) }}">Proceed</a>
        </div>
        <div class="mb-3">
            <a class="btn btn-secondary" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">Cancel</a>
        </div>
    </div>
@endsection
