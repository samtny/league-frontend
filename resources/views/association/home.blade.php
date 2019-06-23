@extends('layouts.full', ['name' => 'home'])

@section('title', $association->name)

@section('favicon')
    @parent
@endsection

@section('content')
    @component('components/page-title')
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
                    <a class="button" href="{{ route('association.submit.score.step1', ['association' => $association]) }}">Submit Scores</a>
                </li>
                <li>
                    <a class="button" href="{{ route('association.standings', ['association' => $association]) }}">Standings</a>
                </li>
                <li>
                    <a class="button" href="{{ route('association.schedule', ['association' => $association]) }}">Schedule</a>
                </li>
            </ul>
        </nav>
    </div>
@endsection
