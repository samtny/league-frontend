@extends('layouts.app')

@section('title', 'Create Association')

@section('content')
    <div class="title m-b-md">
        Create Association
    </div>
    <div class="form">
        <form method="POST" action="/association/create">
            @csrf

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
