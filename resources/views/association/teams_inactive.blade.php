@extends('layouts.admin')

@section('title', $association->name . ' Inactive Teams')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.teams.inactive', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Inactive Teams</h1>
    </div>
    <div class="teams row mb-3">
        <div class="col">
            <?php if (!$association->inactiveTeams->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->inactiveTeams->sortBy('sortName') as $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('team.edit', ['association' => $association, 'team' => $item]) }}">
                        <?php echo ('<div class="team">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No inactive teams for this association.
                </div>
            <?php endif; ?>
        </div>
    </div>
@endsection
