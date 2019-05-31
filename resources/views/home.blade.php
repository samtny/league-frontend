@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="title m-b-md">
        Home
    </div>

    <div class="auth">
        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif
    </div>

    <div class="links">
        <a href="{{ route('standings') }}">Standings</a>
        <a href="{{ route('schedule') }}">Schedule</a>
    </div>
@endsection
