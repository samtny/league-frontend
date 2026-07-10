@extends('layouts.admin')

@section('title', 'Edit Venue')

@section('breadcrumb')
    {{ Breadcrumbs::render('venue.edit', $venue) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $venue->name; ?></h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('venue.update', ['association' => $association, 'venue' => $venue])}}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $venue->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="pinballmap_id">Pinball Map ID</label>
                <input id="pinballmap_id" type="text" class="form-control @error('pinballmap_id') is-invalid @enderror" name="pinballmap_id" value="{{ old('pinballmap_id', $venue->pinballmap_id) }}">
                @error('pinballmap_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Find this by searching your venue at pinballmap.com &mdash; it's the numeric ID in the URL.</small>
            </div>

            <div class="mb-3 form-check">
                <input id="active" type="checkbox" class="form-check-input" name="active" value="1" {{ old('active', $venue->active) ? 'checked' : '' }}>
                <label for="active" class="form-check-label">Active</label>
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Update"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-warning" href="{{ route('venue.deleteConfirm', ['association' => $association, 'venue' => $venue]) }}">Delete Venue</a>
                </div>
            </div>

        </form>
    </div>
@endsection
