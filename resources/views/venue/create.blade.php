@extends('layouts.admin')

@section('title', 'Create Venue')

@section('content')
    <div class="title m-b-md">
        Create Venue
    </div>
    <div class="form">
        <form method="POST" action="{{ route('venue.create', ['association' => $association]) }}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <span class="form-item-help">Enter a name for this Venue</span>
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
