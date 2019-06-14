@extends('layouts.admin')

@section('title', 'Administration')

@section('breadcrumb')
    {{ Breadcrumbs::render('admin') }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Administration</h1>
    </div>
    <div class="associations row">
        <div class="col">
            <h2>{{ __('Associations') }}</h2>
            <?php $associations = \App\Association::get(); ?>
            <div class="list-group">
            <?php foreach ($associations as $association): ?>
                @can('edit', $association)
                    <a class="list-group-item list-group-item-action" href="{{ route('association.view', ['association' => $association]) }}">
                        <?php echo ('<div class="association">' . $association->name . '</div>'); ?>
                    </a>
                @endcan
            <?php endforeach; ?>
            </div>
        </div>
    </div>
@endsection
