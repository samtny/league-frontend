@extends('layouts.admin')

@section('title', $association->name . ' Users')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.users', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Users</h1>
    </div>
    <div class="teams row">
        <div class="col">
            <?php if (!$association->users->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->users->sortBy('name') as $user): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('team.edit', ['association' => $association, 'user' => $user]) }}">
                        <?php echo ('<div class="user">' . $user->name . '</div>'); ?>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="message">
                    No users for this association.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions row mt-4">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('association.user.add', ['association' => $association ]) }}">Add User</a>
        </div>
    </div>
@endsection
