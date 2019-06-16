@extends('layouts.admin')

@section('title', 'Edit User')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.user.edit', $association, $user) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $user->name; ?></h1>
    </div>
    <div class="form row">
        <form class="col" method="POST" action="{{ route('association.user.update', ['association' => $association, 'user' => $user]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-check">
                <input class="form-check-input" name="assoc_admin" type="checkbox" value="assoc_admin" id="assoc_admin" <?php echo $user->isAn('assocadmin') ? ' checked' : ''; ?>>
                <label class="form-check-label" for="assoc_admin">
                    Association Admin
                </label>
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Update"/>
                </div>
            </div>

        </form>
    </div>
@endsection
