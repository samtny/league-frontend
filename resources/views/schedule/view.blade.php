@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.view', $schedule) }}
@endsection

@section('title', __('Schedule :name', ['name' => $schedule->name]))

@section('content')
    <div class="row">
        <h1 class="col">{{ __(':series — :name', ['series' => $schedule->series->name, 'name' => $schedule->name]) }}</h1>
    </div>
    <div class="links row">
        <div class="col">
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('schedule.edit', ['schedule' => $schedule]) }}">Edit Details</a>
                <a class="list-group-item list-group-item-action" href="{{ route('schedule.rounds', ['schedule' => $schedule]) }}">Rounds</a>
            </div>
        </div>
    </div>
@endsection
