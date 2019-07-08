@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.user.token', $association, $user) }}
@endsection

@section('title', 'Token - ' . $user->name)

@section('content')
    <div class="row">
        <h1 class="col"><?php echo 'Token - ' . $user->name; ?></h1>
    </div>
    <div class="form row">
        <div class="col mb-3">
            <form class="col" method="GET" action="{{ route('association.user.view', ['association' => $association, 'user' => $user]) }}">
                @csrf

                <input type="hidden" name="url" value="{{ URL::previous() }}">

                <p>
                    The new token is {{ $token }}
                </p>

                <div class="form-actions">
                    <div class="form-group">
                        <input class="btn btn-primary" id="submit" type="submit" value="Done"/>
                    </div>
                </div>

            </form>
        </div>
    </div>
@endsection
