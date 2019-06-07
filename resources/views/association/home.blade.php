@extends('layouts.full', ['name' => 'home'])

@section('content')
    @component('page-title')
        @slot('title')
            <?php echo $association->name; ?>
        @endslot
    @endcomponent
    <div class="link-buttons">
        <nav class="association-nav">
            <ul>
                <li>
                    <a class="button" href="{{ route('association.submit.score') }}">Submit Scores</a>
                </li>
                <li>
                    <a class="button" href="{{ route('association.standings') }}">Standings</a>
                </li>
                <li>
                    <a class="button" href="{{ route('association.schedule') }}">Schedule</a>
                </li>
            </ul>
        </nav>
    </div>
@endsection
