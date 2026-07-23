@extends('layouts.admin')

@section('title', 'Create Venue')

@section('breadcrumb')
    {{ Breadcrumbs::render('venue.create', $association) }}
@endsection

@section('content')
    <h1>
        Create Venue
    </h1>
    <div class="form">
        <form method="POST" action="{{ route('venue.create', ['association' => $association]) }}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Venue</small>
            </div>

            <div class="mb-3">
                <label>Eligible Divisions</label>
                <small class="form-text text-muted">Which divisions can play here?</small>
                <?php $selectedDivisionIds = old('division_ids', []); ?>
                <?php if (!$association->divisions->isEmpty()): ?>
                    <?php foreach ($association->divisions->sortBy('sequence') as $division): ?>
                        <div class="form-check">
                            <input id="division_<?php echo $division->id; ?>" type="checkbox" class="form-check-input" name="division_ids[]" value="<?php echo $division->id; ?>" <?php if (in_array($division->id, $selectedDivisionIds)) echo 'checked'; ?>>
                            <label for="division_<?php echo $division->id; ?>" class="form-check-label"><?php echo $division->name; ?></label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="message">
                        No divisions for this association.
                    </div>
                <?php endif; ?>
                @error('division_ids')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="pinballmap_id">Pinball Map ID</label>
                <input id="pinballmap_id" type="text" class="form-control @error('pinballmap_id') is-invalid @enderror" name="pinballmap_id" value="{{ old('pinballmap_id') }}">
                @error('pinballmap_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Find this by searching your venue at pinballmap.com &mdash; it's the numeric ID in the URL.</small>
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
