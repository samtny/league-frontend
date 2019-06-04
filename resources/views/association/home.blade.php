@extends('layouts.app')

@section('title', $association->name)

@section('content')
    <div class="title m-b-md">
        <?php echo $association->name; ?>
    </div>
    <div class="message">
        <?php echo $association->name; ?>
    </div>
    <div class="links">
        <a href="{{ route('association.submit.score') }}">Submit Scores</a>
        <a href="{{ route('association.standings') }}">Standings</a>
        <a href="{{ route('association.schedule') }}">Schedule</a>
    </div>
@endsection
