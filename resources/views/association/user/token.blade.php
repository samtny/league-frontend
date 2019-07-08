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
            <form class="col" method="POST" action="{{ route('association.user.token.refresh', ['association' => $association, 'user' => $user]) }}">
                @csrf

                <input type="hidden" name="url" value="{{ URL::previous() }}">

                <p>
                    Hit 'Refresh' to generate a new API token. Note that any existing token will be invalidated. Take careful note of the new token, it will only be viewable one time.
                </p>

                <div class="form-actions">
                    <div class="form-group">
                        <input class="btn btn-primary" id="submit" type="submit" value="Refresh"/>
                    </div>
                </div>

            </form>
        </div>
    </div>
@endsection
