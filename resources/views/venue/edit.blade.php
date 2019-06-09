@extends('layouts.admin')

@section('title', 'Edit Venue')

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $venue->name; ?></h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('venue.update', ['venue' => $venue])}}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ $venue->name }}" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Update"/>
                </div>
                <div class="form-item">
                    <a href="{{ route('venue.deleteConfirm', ['venue' => $venue]) }}">Delete Venue</a>
                </div>
            </div>

        </form>
    </div>
@endsection
