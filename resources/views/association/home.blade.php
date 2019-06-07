@extends('layouts.full', ['name' => 'home'])

@section('content')
    @component('page-title')
        @slot('title')
            <?php echo $association->name; ?>
        @endslot
    @endcomponent
    <?php if (!empty($association->home_image_path)): ?>
    <div class="association-home-image">
        <img src="/storage/<?php echo $association->home_image_path; ?>" alt="<?php echo $association->name . ' logo' ?>" />
    </div>
    <?php endif; ?>
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
