@extends('layouts.admin')

@section('title', 'Create Division')

@section('breadcrumb')
    {{ Breadcrumbs::render('division.create', $association) }}
@endsection

@section('content')
    <h1 class="title">
        Create Division
    </h1>
    <div class="form">
        <form method="POST" action="{{ route('division.create', ['association' => $association]) }}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control" name="name" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Division, like <em>"A Division"</em></small>
            </div>

            <div class="form-group">
                <label for="sequence">Sequence</label>
                <input id="sequence" type="text" class="form-control" name="sequence" class="@error('sequence') is-invalid @enderror">
                @error('sequence')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Use this to order divisions in display, e.g. <em>"1"</em>, <em>"2"</em>, etc., or leave empty for alpha display.</small>
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input class="btn btn-primary" id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
