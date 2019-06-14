@extends('layouts.admin')

@section('title', 'User Management')

@section('breadcrumb')
    {{ Breadcrumbs::render('admin.users') }}
@endsection

@section('content')
    <h1>User Management</h1>
    <div class="users list-group">
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $index => $user): ?>
                <a class="list-group-item" href="/user/<?php echo($user->id); ?>/edit">
                    <?php echo ('<div class="user">' . $user->name . '</div>'); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Users.
            </div>
        <?php endif; ?>
    </div>
@endsection
