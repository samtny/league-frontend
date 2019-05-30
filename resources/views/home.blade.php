@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="title m-b-md">
        Home
    </div>

    <div class="links">
        <a href="{{ route('standings') }}">Standings</a>
        <a href="{{ route('schedule') }}">Schedule</a>
    </div>
@endsection
