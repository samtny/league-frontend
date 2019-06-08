@extends('layouts.admin')

@section('title', 'test')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.divisions', $association) }}
@endsection

@section('content')
    <div class="divisions">
        Association Divisions
        <?php if (!empty($association->divisions)): ?>
            <?php foreach ($association->divisions as $item): ?>
                <a href="{{ route('division.edit', ['association' => $association, 'division' => $item]) }}">
                    <?php echo ('<div class="division">' . $item->name . '</div>'); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="message">
                No Divisions for this association.
            </div>
        <?php endif; ?>
    </div>
@endsection
