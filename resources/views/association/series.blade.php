@extends('layouts.admin')

@section('title', 'test')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.series', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?></h1>
    </div>
    <div class="series row">
        Association Series
        <?php if (!empty($association->series)): ?>
            <?php foreach ($association->series as $index => $item): ?>
                <a href="{{ route('series.edit', ['series' => $item]) }}">
                    <?php echo ('<div class="series">' . $item->name . '</div>'); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Series for this association.
            </div>
        <?php endif; ?>
    </div>
@endsection
