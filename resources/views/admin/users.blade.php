@extends('layouts.admin')

@section('title', 'Administer Users')

@section('content')
    <div class="title m-b-md">
        Administer Users
    </div>

    <div class="auth">
        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif
    </div>

    <div class="users">
        Users
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $index => $user): ?>
                <a href="/user/<?php echo($user->id); ?>">
                    <?php echo ('<div class="user">' . $user->name . '</div>'); ?>
                </a>
                <a href="/user/<?php echo($user->id); ?>/edit">
                    Edit
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Users.
            </div>
        <?php endif; ?>
    </div>
@endsection
