@extends('layouts.admin')

@section('title', 'Archived Schedules')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.schedules.archived', $series) }}
@endsection

@section('content')
    <h1>Archived Schedules</h1>
    <div class="row venues mb-3">
        <div class="col">
        <?php if (!$series->archivedSchedules->isEmpty()): ?>
            <div class="list-group">
            <?php $sortedArchivedSchedules = $series->archivedSchedules->sortBy([
                ['start_date', 'desc'],
                ['end_date', 'asc'],
                ['name', 'asc'],
            ]); ?>
            <?php foreach ($sortedArchivedSchedules as $item): ?>
                <a class="list-group-item list-group-action" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $item ])}}">
                    <?php echo date('m-d-Y', strtotime($item->start_date)); ?> - <?php echo $item->name; ?>
                </a>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="message">
                No archived schedules.
            </div>
        <?php endif; ?>
        </div>
    </div>
@endsection
