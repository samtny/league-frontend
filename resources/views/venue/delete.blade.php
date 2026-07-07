@extends('layouts.admin')

@section('title', 'Delete ' . $venue->name . '?')

@section('breadcrumb')
    {{ Breadcrumbs::render('venue.deleteConfirm', $venue) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Delete <?php echo $venue->name; ?>?</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('venue.delete', ['venue' => $venue])}}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="form-group">
                    <input class="btn btn-primary" id="submit" type="submit" value="Yes"/>
                </div>
                <div class="form-group">
                    <a class="btn btn-secondary" href="{{ route('association.venues', [ 'association' => $venue->association ]) }}">Cancel</a>
                </div>
            </div>

        </form>
    </div>
@endsection
