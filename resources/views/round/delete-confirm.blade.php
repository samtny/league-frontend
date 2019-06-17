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
        <form method="POST" action="{{ route('round.delete', ['schedule' => $schedule, 'round' => $round]) }}">
            @csrf

            <input type="hidden" name="id" value="<?php echo $round->id; ?>">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" class="btn btn-warning" type="submit" value="Delete Round"/>
                </div>
            </div>

        </form>
    </div>
@endsection
