@extends('layouts.admin')

@section('title', 'Delete ' . $association->name . '?')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.deleteConfirm', $association) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Delete <?php echo $association->name; ?>?</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('association.delete', ['association' => $association])}}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Yes"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('admin') }}">Cancel</a>
                </div>
            </div>

        </form>
    </div>
@endsection
