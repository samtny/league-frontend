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
            <?php if (!$association->series->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->series->sortBy(['start_date', 'DESC']) as $index => $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('series.view', ['series' => $item]) }}">
                        <?php echo ('<div class="series">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No Series for this association.
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
