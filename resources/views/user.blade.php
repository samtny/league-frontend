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
                <?php if ($item->association): ?>
                    <a href="{{ route('series.view', ['association' => $item->association, 'series' => $item]) }}">
                        <?php echo ('<div class="series">' . $item->name . '</div>'); ?>
                    </a>
                    <a href="{{ route('series.edit', ['association' => $item->association, 'series' => $item]) }}">
                        Edit
                    </a>
                <?php else: ?>
                    <div class="series"><?php echo $item->name; ?> (no association)</div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Series.
            </div>
        <?php endif; ?>
    </div>

@endsection
