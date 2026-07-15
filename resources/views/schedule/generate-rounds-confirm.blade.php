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
            <div class="message">
                Rounds exist for this Schedule: confirm you want to delete?
            </div>
        </div>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('schedule.generate-rounds.delete', ['association' => $association, 'schedule' => $schedule]) }}">
            @csrf

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-warning" type="submit" value="Confirm Delete"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection
