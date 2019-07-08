@extends('layouts.admin')

@section('title', $association->name . ' Users')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.users', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Users</h1>
    </div>
    <div class="teams row mb-3">
        <div class="col">
            <?php if (!$association->users->isEmpty()): ?>
                <div class="list-group">
                <?php foreach ($association->users->sortBy('name') as $user): ?>
                    <a class="list-group-item list-group-item-action" href="{{ route('association.user.view', ['association' => $association, 'user' => $user]) }}">
                        <?php
                            echo ('<div class="user">' . $user->name . ($user->isAn('assocadmin') ? ' - <em>Admin</em>' : '') . '</div>');
                        ?>
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
    <div class="actions row">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('association.user.add', ['association' => $association ]) }}">Add User</a>
        </div>
    </div>
@endsection
