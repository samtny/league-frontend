@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.view', $series) }}
@endsection

@section('title', $series->name)

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $series->name; ?></h1>
    </div>
    <div class="links row">
        <div class="col">
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('series.edit', ['series' => $series]) }}">Edit Details</a>
                <a class="list-group-item list-group-item-action" href="{{ route('series.schedules', ['series' => $series]) }}">Schedules</a>
            </div>
        </div>
    </div>
@endsection
