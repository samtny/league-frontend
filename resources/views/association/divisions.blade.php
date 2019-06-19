@extends('layouts.admin')

@section('title', $association->name . ' Divisions')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.divisions', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Divisions</h1>
    </div>
    <div class="divisions row mb-3">
        <div class="col">
            <?php if (!$association->divisions->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->divisions->sortBy('sequence') as $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('division.edit', ['association' => $association, 'division' => $item]) }}">
                        <?php echo ('<div class="division">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No Divisions for this association.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions row">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('division.create', ['association' => $association]) }}">Create New Division</a>
        </div>
    </div>
@endsection
