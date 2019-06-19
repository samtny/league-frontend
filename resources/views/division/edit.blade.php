@extends('layouts.admin')

@section('title', 'Edit Division')

@section('breadcrumb')
    {{ Breadcrumbs::render('division.edit', $association, $division) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $division->name; ?></h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('division.update', ['association' => $association, 'division' => $division])}}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $division->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Division, like <em>"A Division"</em></small>
            </div>

            <div class="form-group">
                <label for="sequence">Sequence</label>
                <input id="sequence" type="text" class="form-control @error('sequence') is-invalid @enderror" name="sequence" value="{{ old('sequence', $division->sequence) }}">
                @error('sequence')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Use this to order divisions in display, e.g. <em>"1"</em>, <em>"2"</em>, etc., or leave empty for alpha display.</small>
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input class="btn btn-primary" id="submit" type="submit" value="Update"/>
                </div>
                <div class="form-group">
                    <a class="btn btn-warning" href="{{ route('division.deleteConfirm', ['association' => $association, 'division' => $division]) }}">Delete Division</a>
                </div>
            </div>

        </form>
    </div>
@endsection
