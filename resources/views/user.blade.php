@extends('layouts.admin')

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
        <?php if (!empty($associations)): ?>
            <?php foreach ($associations as $index => $association): ?>
                <a href="/association/<?php echo($association->id); ?>">
                    <?php echo ('<div class="association">' . $association->name . '</div>'); ?>
                </a>
                <a href="/association/<?php echo($association->id); ?>/edit">
                    Edit
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Associations.
            </div>
        <?php endif; ?>
        <div class="links">
            <a href="{{ route('association.create') }}">Create Association</a>
        </div>
    </div>

    <div class="series">
        Series
        <?php if (!empty($series)): ?>
            <?php foreach ($series as $index => $item): ?>
                <a href="/series/<?php echo($item->id); ?>">
                    <?php echo ('<div class="series">' . $item->name . '</div>'); ?>
                </a>
                <a href="/series/<?php echo($item->id); ?>/edit">
                    Edit
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Series.
            </div>
        <?php endif; ?>
        <div class="links">
            <a href="{{ route('series.create') }}">Create Series</a>
        </div>
    </div>

@endsection
