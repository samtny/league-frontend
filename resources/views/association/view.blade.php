@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.view', $association) }}
@endsection

@section('title', $association->name)

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?></h1>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <h2>Administer</h2>
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('association.edit', ['association' => $association]) }}">Edit Details</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.divisions', ['association' => $association]) }}">Divisions</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.teams', ['association' => $association]) }}">Teams</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.venues', ['association' => $association]) }}">Venues</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.series', ['association' => $association]) }}">Series</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.users', ['association' => $association]) }}">Users</a>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="row">
                <div class="col mb-3">
                    <h2>Active Rounds</h2>
                    <div class="list-group">
                    @forelse ($association->activeRounds->sortBy('start_date') as $round)
                    <a class="list-group-item list-group-item-action" href="{{ route('round.edit', ['schedule' => $round->schedule, 'round' => $round]) }}">
                        <?php echo !empty($round->series) ? $round->series->name : '[no series]'; ?> - <?php echo $round->name; ?> - <?php echo $round->start_date->format('Y-m-d'); ?>
                    </a>
                    @empty
                    There are no active rounds.
                    @endforelse
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <h2>Score Submissions</h2>
                    <div class="list-group">
                    <?php $submissions = $association->resultSubmissions->where('approved', 0); ?>
                    <?php if(!($submissions->isEmpty())): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('result_submissions.list', ['association' => $association]) }}">
                        Score Submissions
                        <span class="badge badge-primary badge-pill"><?php echo count($submissions); ?></span>
                    </a>
                    <?php else: ?>
                    There are no score submissions to review.
                    <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <h2>Messages</h2>
                    <div class="list-group">
                    <?php $messages = $association->contactSubmissions->where('archived', 0); ?>
                    <?php if(!($messages->isEmpty())): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('contact_submissions.list', ['association' => $association]) }}">
                        Contact Submissions
                        <span class="badge badge-primary badge-pill"><?php echo count($messages); ?></span>
                    </a>
                    <?php else: ?>
                    There are no messages waiting.
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="rounds row">

    </div>
@endsection
