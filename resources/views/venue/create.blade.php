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

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control" name="name" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Venue</small>
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input class="btn btn-primary" id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
