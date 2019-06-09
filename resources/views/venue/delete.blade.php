@extends('layouts.admin')

@section('title', 'Delete ' . $venue->name . '?')

@section('content')
    <div class="title m-b-md">
        <?php echo 'Delete ' . $venue->name . '?'; ?>
    </div>
    <div class="links">
        <a href="{{ route('association.view', [ 'association' => $venue->association ]) }}">Cancel</a>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('venue.delete', ['venue' => $venue])}}">
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
