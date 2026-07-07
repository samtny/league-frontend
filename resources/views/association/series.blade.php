@extends('layouts.admin')

@section('title', $association->name . ' Series')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.series', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Series</h1>
    </div>
    <div class="series row mb-3">
        <div class="col">
            <h2 class="text-muted">Active</h2>
            <?php if (!$association->activeSeries->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->activeSeries->sortBy(['start_date', 'DESC']) as $index => $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('series.view', ['series' => $item]) }}">
                        <?php echo ('<div class="series">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message message-empty">
                    No Series for this association.
                </div>
            <?php endif; ?>
            <?php if (!$association->archivedSeries->isEmpty()): ?>
                <h2 class="text-muted mt-3">Archived</h2>
                <div class="list-group">
                    <a class="list-group-item list-group-item-action" href="{{ route('association.series.archived', ['association' => $association]) }}">
                        View
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions row">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('series.create') }}">Create New Series</a>
        </div>
    </div>
@endsection
