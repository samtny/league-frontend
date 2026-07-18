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
            <?php $teamsByDivision = $association->inactiveTeams->sortBy('sortName')->groupBy(function ($team) { return $team->division ? $team->division->id : 0; }); ?>
            <?php if (!$association->inactiveTeams->isEmpty()): ?>
                <?php foreach ($association->divisions->sortBy('sequence') as $division): ?>
                    <?php if ($teamsByDivision->has($division->id)): ?>
                        <h3 class="text-muted"><?php echo $division->name; ?></h3>
                        <div class="list-group mb-3">
                        <?php foreach ($teamsByDivision[$division->id] as $item): ?>
                            <a class="list-group-item list-group-item-action" href="{{ route('team.edit', ['association' => $association, 'team' => $item]) }}">
                                <?php echo ('<div class="team">' . $item->name . '</div>'); ?>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($teamsByDivision->has(0)): ?>
                    <h3 class="text-muted">(no division)</h3>
                    <div class="list-group mb-3">
                    <?php foreach ($teamsByDivision[0] as $item): ?>
                        <a class="list-group-item list-group-item-action" href="{{ route('team.edit', ['association' => $association, 'team' => $item]) }}">
                            <?php echo ('<div class="team">' . $item->name . '</div>'); ?>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="message">
                    No inactive teams for this association.
                </div>
            <?php endif; ?>
        </div>
    </div>
@endsection
