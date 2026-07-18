@extends('layouts.admin')

@section('title', 'Schedules')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.schedules', $series) }}
@endsection

@section('content')
    <h1>Schedules</h1>
    <div class="row venues mb-3">
        <div class="col">
        <?php $schedulesByDivision = $series->activeSchedules->sortBy('sequence')->groupBy(function ($schedule) { return $schedule->division ? $schedule->division->id : 0; }); ?>
        <?php if (!empty($series->activeSchedules)): ?>
            <?php foreach ($series->association->divisions->sortBy('sequence') as $division): ?>
                <?php if ($schedulesByDivision->has($division->id)): ?>
                    <h2 class="text-muted"><?php echo $division->name; ?></h2>
                    <div class="list-group mb-3">
                    <?php foreach ($schedulesByDivision[$division->id] as $item): ?>
                        <a class="list-group-item list-group-action" href="{{ route('schedule.view', ['association' => $series->association, 'schedule' => $item ])}}">
                            {{ $item->name }}
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($schedulesByDivision->has(0)): ?>
                <h2 class="text-muted">(no division)</h2>
                <div class="list-group mb-3">
                <?php foreach ($schedulesByDivision[0] as $item): ?>
                    <a class="list-group-item list-group-action" href="{{ route('schedule.view', ['association' => $series->association, 'schedule' => $item ])}}">
                        {{ $item->name }}
                    </a>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="message">
                No schedules.
            </div>
        <?php endif; ?>
        <?php if (!$series->archivedSchedules->isEmpty()): ?>
            <h2 class="text-muted mt-3">Archived</h2>
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('series.schedules.archived', ['association' => $association, 'series' => $series]) }}">
                    View
                </a>
            </div>
        <?php endif; ?>
        </div>
    </div>
    <div class="actions row">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('schedule.create', [ 'association' => $series->association, 'series' => $series ]) }}">Create Schedule</a>
        </div>
    </div>
@endsection
