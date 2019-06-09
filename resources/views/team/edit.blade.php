@extends('layouts.admin')

@section('title', 'Edit Team')

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $team->name; ?></h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('team.update', ['association' => $team->association, 'team' => $team])}}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ $team->name }}" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
                <div class="form-item">
                    <a href="{{ route('team.deleteConfirm', ['association' => $team->association, 'team' => $team]) }}">Delete Team</a>
                </div>
            </div>

        </form>
    </div>
@endsection
