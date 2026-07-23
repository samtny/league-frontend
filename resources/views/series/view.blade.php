@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.view', $series) }}
@endsection

@section('title', $series->name)

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $series->name; ?></h1>
    </div>
    <div class="links row">
        <div class="col">
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('series.edit', ['association' => $series->association, 'series' => $series]) }}">Edit Details</a>
            </div>
        </div>
    </div>
    <h2 class="text-muted mt-3">Schedules</h2>
    <div class="row">
        <p class="col text-muted">Create a Schedule for each Division you want to play in this Series. Schedules hold (weekly) Rounds and Team / Venue assignments. Make sure your <a href="{{ route('association.divisions', ['association' => $series->association]) }}">Divisions</a>, <a href="{{ route('association.venues', ['association' => $series->association]) }}">Venues</a> and <a href="{{ route('association.teams', ['association' => $series->association]) }}">Teams</a> are up-to-date (activate / deactivate the ones you want now) before generating a Schedule for the best experience.</p>
    </div>
    <div class="row venues mb-3">
        <div class="col">
        <?php $schedulesByDivision = $series->activeSchedules->sortBy('sequence')->groupBy(function ($schedule) { return $schedule->division ? $schedule->division->id : 0; }); ?>
        <?php if (!empty($series->activeSchedules)): ?>
            <?php foreach ($series->association->divisions->sortBy('sequence') as $division): ?>
                <?php if ($schedulesByDivision->has($division->id)): ?>
                    <h3 class="text-muted"><?php echo $division->name; ?></h3>
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
                <h3 class="text-muted">(no division)</h3>
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
            <h3 class="text-muted mt-3">Archived</h3>
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
