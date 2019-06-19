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
        <form method="POST" action="{{ route('venue.update', ['venue' => $venue])}}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control" name="name" value="{{ $venue->name }}" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input class="btn btn-primary" id="submit" type="submit" value="Update"/>
                </div>
                <div class="form-group">
                    <a class="btn btn-warning" href="{{ route('venue.deleteConfirm', ['venue' => $venue]) }}">Delete Venue</a>
                </div>
            </div>

        </form>
    </div>
@endsection
