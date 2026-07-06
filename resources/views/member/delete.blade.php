@extends('layouts.admin')

@section('title', 'Delete ' . $member->name . '?')

@section('content')
    <div class="title m-b-md">
        <?php echo 'Delete ' . $member->name . '?'; ?>
    </div>
    <div class="links">
        <a href="{{ route('team.roster', [ 'association' => $member->association, 'team' => $member->team ]) }}">Cancel</a>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('member.delete', ['association' => $member->association, 'member' => $member])}}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Yes"/>
                </div>
            </div>

        </form>
    </div>
@endsection
