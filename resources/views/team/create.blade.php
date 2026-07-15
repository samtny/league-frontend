@extends('layouts.admin')

@section('title', 'Create Team')

@section('breadcrumb')
    {{ Breadcrumbs::render('team.create', $association) }}
@endsection

@section('content')
    <h1>
        Create Team
    </h1>
    <div class="form">
        <form method="POST" action="{{ route('team.create', ['association' => $association]) }}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <input type="hidden" name="association_id" value="<?php echo($association->id); ?>">

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Team</small>
            </div>

            <div class="mb-3">
                <label for="venue_id">Home Venue</label>
                <select class="form-control" id="venue_id" name="venue_id">
                    <option value="">- No Venue -</option>
                    <?php foreach($association->venues->sortBy('name') as $venue): ?>
                    <option value="<?php echo $venue->id; ?>"><?php echo $venue->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3 form-check">
                <input id="active" type="checkbox" class="form-check-input" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}>
                <label for="active" class="form-check-label">Active</label>
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
