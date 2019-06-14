@extends('layouts.admin')

@section('title', 'Administration')

@section('breadcrumb')
    {{ Breadcrumbs::render('admin') }}
@endsection

@section('content')
    <h1>Administration</h1>
    <div class="associations row">
        <div class="col-md-12">
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
    <div class="users row">
        <div class="col-md-12">
            <h2>{{ __('Users') }}</h2>
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('admin.users') }}">
                    {{ __('User Management') }}
                </a>
            </div>
        </div>
    </div>
@endsection
