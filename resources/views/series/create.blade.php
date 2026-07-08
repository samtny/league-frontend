@extends('layouts.admin')

@section('title', 'Create Series')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.create', $association) }}
@endsection

@section('content')
    <h1>
        Create Series
    </h1>
    <div class="form">
        <form method="POST" action="{{ route('series.create', ['association' => $association]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
