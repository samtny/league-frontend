@extends('layouts.admin')

@section('breadcrumb')
    {{ Breadcrumbs::render('association', $association) }}
@endsection

@section('title', $association->name)

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $association->name; ?></h1>
    </div>
    <div class="links row">
        <div class="col">
            <div class="list-group">
                <a class="list-group-item list-group-item-action" href="{{ route('association.edit', ['association' => $association]) }}">Edit Details</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.divisions', ['association' => $association]) }}">Divisions</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.teams', ['association' => $association]) }}">Teams</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.venues', ['association' => $association]) }}">Venues</a>
                <a class="list-group-item list-group-item-action" href="{{ route('association.series', ['association' => $association]) }}">Series</a>
            </div>
        </div>
    </div>
@endsection
