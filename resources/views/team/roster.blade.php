@extends('layouts.admin')

@section('title', $team->name . ' Roster')

@section('breadcrumb')
    {{ Breadcrumbs::render('team.roster', $team) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $team->name; ?> - Roster</h1>
    </div>
    <div class="roster row mb-3">
        <div class="col">
            <?php if (!$team->roster->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($team->roster->sortBy('name') as $item): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('member.edit', ['association' => $association, 'member' => $item]) }}">
                        <?php echo ('<div class="member">' . $item->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No members for this team.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions row">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('member.create', ['association' => $association, 'team' => $team ]) }}">Add Member</a>
        </div>
    </div>
@endsection
