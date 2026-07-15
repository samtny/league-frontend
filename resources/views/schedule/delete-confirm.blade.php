@extends('layouts.admin')

@section('title', 'Delete Schedule')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.edit', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Delete Schedule</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('schedule.delete', ['association' => $schedule->association, 'schedule' => $schedule]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="mb-3">
                    <input id="submit" class="btn btn-warning" type="submit" value="Delete Schedule"/>
                </div>
            </div>

        </form>
    </div>
@endsection
