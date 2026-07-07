@extends('layouts.admin')

@section('title', 'Delete ' . $team->name . '?')

@section('breadcrumb')
    {{ Breadcrumbs::render('team.deleteConfirm', $team) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Delete <?php echo $team->name; ?>?</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('team.delete', ['association' => $team->association, 'team' => $team])}}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Yes"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('association.teams', [ 'association' => $team->association ]) }}">Cancel</a>
                </div>
            </div>

        </form>
    </div>
@endsection
