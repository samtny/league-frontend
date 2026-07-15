@extends('layouts.admin')

@section('title', $association->name . ' Venues')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.venues', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Venues</h1>
    </div>
    <div class="row venues mb-3">
        <div class="col">
            <h2 class="text-muted">Active</h2>
            <?php if (!$association->activeVenues->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->activeVenues->sortBy('name') as $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('venue.edit', ['association' => $association, 'venue' => $item]) }}">
                        <?php echo ('<div class="venue">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No venues for this association.
                </div>
            <?php endif; ?>
            <?php if (!$association->inactiveVenues->isEmpty()): ?>
                <h2 class="text-muted mt-3">Inactive</h2>
                <div class="list-group">
                    <a class="list-group-item list-group-item-action" href="{{ route('association.venues.inactive', ['association' => $association]) }}">
                        View
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions row">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('venue.create', ['association' => $association ]) }}">Create New Venue</a>
        </div>
    </div>
@endsection
