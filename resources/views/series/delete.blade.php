@extends('layouts.admin')

@section('title', 'Delete ' . $series->name . '?')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.deleteConfirm', $series) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Delete <?php echo $series->name; ?>?</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('series.delete', ['series' => $series]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Yes"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('association.series', ['association' => $series->association]) }}">Cancel</a>
                </div>
            </div>

        </form>
    </div>
@endsection
