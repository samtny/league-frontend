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
        <div class="col-md-5">
            <a class="btn btn-primary btn-block" href="{{ route('association.edit', ['association' => $association]) }}">Edit Details</a>
            <a class="btn btn-primary btn-block" href="{{ route('association.series', ['association' => $association]) }}">Series</a>
        </div>
    </div>
@endsection
