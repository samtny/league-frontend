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

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $team->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="venue_id">Home Venue</label>
                <select class="form-control" id="venue_id" name="venue_id">
                    <option value="">- No Venue -</option>
                    <?php
                        $venues = $team->association->activeVenues;
                        if ($team->venue_id && !$venues->contains('id', $team->venue_id)) {
                            $venues = $venues->push($team->homeVenue);
                        }
                        $venues = $venues->sortBy('name');
                    ?>
                    <?php foreach($venues as $venue): ?>
                    <option value="<?php echo $venue->id; ?>"<?php echo $team->venue_id == $venue->id ? ' selected' : ''; ?>><?php echo $venue->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <div class="list-group">
                        <a class="list-group-item list-group-item-action" href="{{ route('team.roster', ['association' => $team->association, 'team' => $team]) }}">Roster</a>
                    </div>
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="active" name="active" value="1"<?php echo $team->active ? ' checked' : ''; ?>>
                <label class="form-check-label" for="active">Active</label>
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Update"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-warning" href="{{ route('team.deleteConfirm', ['association' => $team->association, 'team' => $team]) }}">Delete Team</a>
                </div>
            </div>

        </form>
    </div>
@endsection
