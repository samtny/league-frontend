@extends('layouts.admin')

@section('title', $association->name . ' Venues')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.venues', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Venues</h1>
    </div>
    <div class="row venues">
        <div class="col">
            <?php if (!$association->venues->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->venues as $item): ?>
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
        </div>
    </div>
    <div class="actions row mt-4">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('venue.create', ['association' => $association ]) }}">Create New Venue</a>
        </div>
    </div>
@endsection
