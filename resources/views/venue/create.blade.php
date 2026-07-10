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
