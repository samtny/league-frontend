@extends('layouts.admin')

@section('title', 'Confirm Schedule Update')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.update.confirm', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Confirm Schedule Update</h1>
    </div>
    <div class="row mb-3">
        <div class="col">
            <div class="message">
                The Match Weekday, Start Date, or End Date you submitted no longer match the Rounds that exist for this Schedule. Continuing will delete the existing (empty) Rounds and regenerate them to match. Confirm you want to proceed?
            </div>
        </div>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('schedule.update.confirm.accept', ['association' => $association, 'schedule' => $schedule]) }}">
            @csrf

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-warning" type="submit" value="Confirm Delete &amp; Regenerate"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('schedule.edit', ['association' => $association, 'schedule' => $schedule]) }}">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection
