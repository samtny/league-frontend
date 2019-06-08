@extends('layouts.admin')

@section('title', $association->name . ' Teams')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.teams', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Teams</h1>
    </div>
    <div class="teams row">
        <div class="col">
            <?php if (!$association->teams->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->teams->sortBy('name') as $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('team.edit', ['association' => $association, 'team' => $item]) }}">
                        <?php echo ('<div class="team">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No teams for this association.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions row mt-4">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('team.create', ['association' => $association ]) }}">Create New Team</a>
        </div>
    </div>
@endsection
