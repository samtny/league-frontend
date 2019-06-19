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

            <div class="form-group">
                <label for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="New Round">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input id="start_date" class="form-control @error('start_date') is-invalid @enderror" type="date" name="start_date">
                @error('start_date')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="end_date">End Date</label>
                <input id="end_date" class="form-control" type="date" name="end_date">
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" class="btn btn-primary" type="submit" value="Create"/>
                </div>
            </div>

        </form>
    </div>
@endsection
