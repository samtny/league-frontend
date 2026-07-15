@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.view', $schedule) }}
@endsection

@section('title', __('Schedule :name', ['name' => $schedule->name]))

@section('content')
    <div class="row">
        <h1 class="col">{{ __(':series — :name', ['series' => $schedule->series->name, 'name' => $schedule->name]) }}</h1>
    </div>
    <div class="links row">
        <div class="col">
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('schedule.edit', ['association' => $schedule->association, 'schedule' => $schedule]) }}">Edit</a>
                <a class="list-group-item list-group-item-action" href="{{ route('schedule.generate-rounds', ['association' => $schedule->association, 'schedule' => $schedule]) }}">Generate Rounds</a>
            </div>
            <h2 class="text-muted mt-3">Rounds</h2>
            <?php if (!$schedule->rounds->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($schedule->rounds->sortBy('start_date') as $round): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('round.edit', ['association' => $schedule->association, 'schedule' => $schedule, 'round' => $round]) }}">
                        <?php echo ('<div class="round">' . (!empty($round->name) ? ($round->name . ' - ') : '') . (!empty($round->start_date) ? $round->start_date->format('m-d-Y') : '') . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No rounds exist for this Schedule
                </div>
            <?php endif; ?>
        </div>
    </div>
@endsection
