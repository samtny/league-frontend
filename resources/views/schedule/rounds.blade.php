@extends('layouts.admin')

@section('title', $schedule->name . ' Rounds')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.rounds', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $schedule->name; ?> - Rounds</h1>
    </div>
    <div class="row rounds mb-3">
        <div class="col">
            <?php if (!$schedule->rounds->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($schedule->rounds->sortBy('start_date') as $round): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('round.edit', ['schedule' => $schedule, 'round' => $round]) }}">
                        <?php echo ('<div class="round">' . (!empty($round->name) ? ($round->name . ' - ') : '') . (!empty($round->start_date) ? $round->start_date->format('m-d-Y') : '') . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No rounds for this schedule.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="row actions">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('round.create', ['schedule' => $schedule]) }}">Create Round</a>
        </div>
    </div>
@endsection
