@extends('layouts.admin')

@section('title', 'Delete Round')

@section('breadcrumb')
    {{ Breadcrumbs::render('round.edit', $schedule, $round) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Delete Round</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('round.delete', ['association' => $schedule->association, 'schedule' => $schedule, 'round' => $round]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="mb-3">
                    <input id="submit" class="btn btn-warning" type="submit" value="Delete Round"/>
                </div>
            </div>

        </form>
    </div>
@endsection
