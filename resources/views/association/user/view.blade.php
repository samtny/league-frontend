@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.user.view', $association, $user) }}
@endsection

@section('title', $user->name)

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $user->name; ?></h1>
    </div>
    <div class="links row">
        <div class="col">
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('association.user.edit', ['associaiton' => $association, 'user' => $user]) }}">Edit Details</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.user.token', ['associaiton' => $association, 'user' => $user]) }}">API Token</a>
            </div>
        </div>
    </div>
@endsection
