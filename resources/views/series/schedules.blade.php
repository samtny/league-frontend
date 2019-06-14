@extends('layouts.admin')

@section('title', 'Schedules')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.schedules', $series) }}
@endsection

@section('content')
    <?php if (!empty($series->schedules)): ?>
    <div class="list-group">
        <?php foreach ($series->schedules as $index => $item): ?>
            <a class="list-group-item list-group-action" href="{{ route('schedule.edit', ['schedule' => $item ])}}">
                {{ $item->name }}
            </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="message">
            No schedules.
        </div>
    <?php endif; ?>
    <div class="links">
        <a class="btn btn-primary" href="{{ route('schedule.create', [ 'series' => $series ]) }}">Create Schedule</a>
    </div>
@endsection
