@extends('layouts.app')

@section('title', 'Deleted Associations')

@section('content')
    <div class="title m-b-md">
        Deleted Associations
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
        <?php if (!empty($associations)): ?>
            <?php foreach ($associations as $index => $association): ?>
                <a href="/association/<?php echo($association->id); ?>">
                    <?php echo ('<div class="association">' . $association->name . '</div>'); ?>
                </a>
                <a href="/association/<?php echo($association->id); ?>/undelete">
                    Undelete
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No deleted associations.
            </div>
        <?php endif; ?>
    </div>
@endsection
