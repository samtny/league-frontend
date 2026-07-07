@extends('layouts.admin')

@section('title', 'Delete ' . $division->name . '?')

@section('breadcrumb')
    {{ Breadcrumbs::render('division.deleteConfirm', $division->association, $division) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Delete <?php echo $division->name; ?>?</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('division.delete', ['association' => $division->association, 'division' => $division])}}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Yes"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('association.divisions', [ 'association' => $division->association ]) }}">Cancel</a>
                </div>
            </div>

        </form>
    </div>
@endsection
