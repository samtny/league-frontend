@extends('layouts.full', ['name' => 'home'])

@section('title', $association->name)

@section('favicon')
<!-- TODO: make this dynamic -->
<link rel="apple-touch-icon" sizes="180x180" href="/storage/favicon/slope/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/storage/favicon/slope/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/storage/favicon/slope/favicon-16x16.png">
<link rel="manifest" href="/storage/favicon/slope/site.webmanifest">
<link rel="mask-icon" href="/storage/favicon/slope/safari-pinned-tab.svg" color="#5bbad5">
<link rel="shortcut icon" href="/storage/favicon/slope/favicon.ico">
<meta name="msapplication-TileColor" content="#ffc40d">
<meta name="msapplication-config" content="/storage/favicon/slope/browserconfig.xml">
<meta name="theme-color" content="#ebebeb">
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
                    <a class="button" href="{{ route('association.submit.score.step1') }}">Submit Scores</a>
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
