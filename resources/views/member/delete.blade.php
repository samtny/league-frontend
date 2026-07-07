@extends('layouts.admin')

@section('title', 'Delete ' . $member->name . '?')

@section('breadcrumb')
    {{ Breadcrumbs::render('member.deleteConfirm', $member) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Delete <?php echo $member->name; ?>?</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('member.delete', ['association' => $member->association, 'member' => $member])}}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Yes"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('team.roster', [ 'association' => $member->association, 'team' => $member->team ]) }}">Cancel</a>
                </div>
            </div>

        </form>
    </div>
@endsection
