@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.view', $association) }}
@endsection

@section('title', $association->name)

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?></h1>
    </div>
    <div class="links row mb-3">
        <div class="col">
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('association.edit', ['association' => $association]) }}">Edit Details</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.divisions', ['association' => $association]) }}">Divisions</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.teams', ['association' => $association]) }}">Teams</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.venues', ['association' => $association]) }}">Venues</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.series', ['association' => $association]) }}">Series</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.users', ['association' => $association]) }}">Users</a>
                <?php if(!($association->resultSubmissions->where('approved', 0)->isEmpty())): ?>
                <a class="list-group-item list-group-item-action" href="{{ route('result_submissions.list', ['association' => $association]) }}">Score Submissions</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="submissions row mb-3">
        <div class="col">
            <h2>Score Submissions</h2>
            <div class="list-group">
            <?php if(!($association->resultSubmissions->where('approved', 0)->isEmpty())): ?>
            <a class="list-group-item list-group-item-action" href="{{ route('result_submissions.list', ['association' => $association]) }}">Score Submissions</a>
            <?php else: ?>
            There are no score submissions to review.
            <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="rounds row mb-3">
        <div class="col">
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
@endsection
