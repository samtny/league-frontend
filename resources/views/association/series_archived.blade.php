@extends('layouts.admin')

@section('title', $association->name . ' Archived Series')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.series.archived', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Archived Series</h1>
    </div>
    <div class="series row mb-3">
        <div class="col">
            <?php if (!$association->archivedSeries->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->archivedSeries->sortBy(['start_date', 'DESC']) as $index => $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('series.view', ['association' => $association, 'series' => $item]) }}">
                        <?php echo ('<div class="series">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message message-empty">
                    No Archived Series for this association.
                </div>
            <?php endif; ?>
        </div>
    </div>
@endsection
