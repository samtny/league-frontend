@extends('layouts.admin')

@section('title', $association->name . ' Add User')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.user.add', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?> - Add User</h1>
    </div>
    <div class="teams row">
        <div class="col">
            This doesn't do anything right now.
        </div>
    </div>
    <div class="actions row mt-4">
        <div class="col">
            <a class="btn btn-primary" href="{{ route('team.create', ['association' => $association ]) }}">Create New Team</a>
        </div>
    </div>
@endsection
