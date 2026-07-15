@extends('layouts.admin')

@section('title', $association->name . ' Inactive Venues')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.venues.inactive', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Inactive Venues</h1>
    </div>
    <div class="row venues mb-3">
        <div class="col">
            <?php if (!$association->inactiveVenues->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->inactiveVenues->sortBy('name') as $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('venue.edit', ['association' => $association, 'venue' => $item]) }}">
                        <?php echo ('<div class="venue">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No inactive venues for this association.
                </div>
            <?php endif; ?>
        </div>
    </div>
@endsection
