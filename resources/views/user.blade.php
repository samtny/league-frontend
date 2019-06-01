@extends('layouts.app')

@section('title', 'User')

@section('content')
    <div class="title m-b-md">
        User - <?php echo $user->name; ?>
    </div>

    <div class="auth">
        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif
    </div>

    <div class="associations">
        Associations
        <?php foreach ($associations as $index => $association): ?>
            <a href="/association/<?php echo($association->id); ?>/edit">
               <?php echo ('<div class="association">' . $association->name . '</div>'); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="links">
        <a href="{{ route('association.create') }}">Create Association</a>
    </div>
@endsection
