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
                <a href="{{ route('association.edit', ['association' => $association]) }}">
                    Edit
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Associations.
            </div>
        <?php endif; ?>
        @can ('create', App\Association::class)
        <div class="links">
            <a href="{{ route('association.create') }}">Create Association</a>
        </div>
        @endcan
    </div>

    <div class="series">
        Series
        <?php if (!empty($series)): ?>
            <?php foreach ($series as $index => $item): ?>
                <a href="{{ route('series.view', ['series' => $item]) }}">
                    <?php echo ('<div class="series">' . $item->name . '</div>'); ?>
                </a>
                <a href="{{ route('series.edit', ['series' => $item]) }}">
                    Edit
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Series.
            </div>
        <?php endif; ?>
        @can ('create', App\Series::class)
        <div class="links">
            <a href="{{ route('series.create') }}">Create Series</a>
        </div>
        @endcan
    </div>

@endsection
