@extends('layouts.admin')

@section('title', 'Edit Team')

@section('breadcrumb')
    {{ Breadcrumbs::render('team.edit', $team) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $team->name; ?></h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('team.update', ['association' => $team->association, 'team' => $team])}}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $team->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="venue_id">Home Venue</label>
                <select class="form-control" id="venue_id" name="venue_id">
                    <option value="">- No Venue -</option>
                    <?php foreach($team->association->venues->sortBy('name') as $venue): ?>
                    <option value="<?php echo $venue->id; ?>"<?php echo $team->venue_id == $venue->id ? ' selected' : ''; ?>><?php echo $venue->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input class="btn btn-primary" id="submit" type="submit" value="Update"/>
                </div>
                <div class="form-group">
                    <a class="btn btn-warning" href="{{ route('team.deleteConfirm', ['association' => $team->association, 'team' => $team]) }}">Delete Team</a>
                </div>
            </div>

        </form>
    </div>
@endsection
