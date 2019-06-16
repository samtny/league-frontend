@extends('layouts.admin')

@section('title', 'Create Round')

@section('breadcrumb')
    {{ Breadcrumbs::render('round.create', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Create Round</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('round.store', ['schedule' => $schedule]) }}">
            @csrf

            <input type="hidden" name="id" value="<?php echo $schedule->id; ?>">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="row">
                <div class="col-md-3">
                    <label for="start_date">Start Date</label>
                    <input id="start_date" class="form-control" type="date" name="start_date">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <label for="end_date">End Date</label>
                    <input id="end_date" class="form-control" type="date" name="end_date">
                </div>
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" class="form-control" type="submit" value="Create"/>
                </div>
            </div>

        </form>
    </div>
@endsection
