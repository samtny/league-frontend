@extends('layouts.admin')

@section('title', $association->name . ' Teams')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.teams', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Teams</h1>
    </div>
    <div class="teams row mb-3">
        <div class="col">
            <h2 class="text-primary">Active</h2>
            <?php $teamsByDivision = $association->activeTeams->sortBy('sortName')->groupBy(function ($team) { return $team->division ? $team->division->id : 0; }); ?>
            <?php if (!$association->activeTeams->isEmpty()): ?>
                <?php foreach ($association->divisions->sortBy('sequence') as $division): ?>
                    <?php if ($teamsByDivision->has($division->id)): ?>
                        <h3 class="text-muted"><?php echo $division->name; ?></h3>
                        <div class="list-group mb-3">
                        <?php foreach ($teamsByDivision[$division->id] as $item): ?>
                            <?php $label = ($item->homeVenue && $item->homeVenue->name !== $item->name) ? $item->name . ' - ' . $item->homeVenue->name : $item->name; ?>
                            <a class="list-group-item list-group-item-action" href="{{ route('team.edit', ['association' => $association, 'team' => $item]) }}">
                                <?php echo ('<div class="team">' . $label . '</div>'); ?>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($teamsByDivision->has(0)): ?>
                    <h3 class="text-muted">(no division)</h3>
                    <div class="list-group mb-3">
                    <?php foreach ($teamsByDivision[0] as $item): ?>
                        <?php $label = ($item->homeVenue && $item->homeVenue->name !== $item->name) ? $item->name . ' - ' . $item->homeVenue->name : $item->name; ?>
                        <a class="list-group-item list-group-item-action" href="{{ route('team.edit', ['association' => $association, 'team' => $item]) }}">
                            <?php echo ('<div class="team">' . $label . '</div>'); ?>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="message">
                    No teams for this association.
                </div>
            <?php endif; ?>
            <?php if (!$association->inactiveTeams->isEmpty()): ?>
                <h2 class="text-primary mt-3">Inactive</h2>
                <div class="list-group">
                    <a class="list-group-item list-group-item-action" href="{{ route('association.teams.inactive', ['association' => $association]) }}">
                        View
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions row">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('team.create', ['association' => $association ]) }}">Create New Team</a>
        </div>
    </div>
@endsection
