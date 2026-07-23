@extends('layouts.admin')

@section('title', 'Edit Series')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.edit', $series) }}
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="title m-b-md h1">
                Edit Series – <?php echo $series->name; ?>
            </div>
        </div>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('series.update', ['association' => $association, 'series' => $series ]) }}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $series->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" name="archived" type="checkbox" value="1" id="archived" <?php echo old('archived', $series->archived) ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="archived">
                        Archived
                    </label>
                    <small class="form-text text-muted">When checked, this series will not show in the Series list.</small>
                </div>
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <button class="btn btn-primary" id="submit" type="submit">Update</button>
                </div>
                <div class="mb-3">
                    <a class="btn btn-warning" href="{{ route('series.deleteConfirm', [ 'association' => $association, 'series' => $series ]) }}">Delete Series</a>
                </div>
            </div>
        </form>
    </div>

@endsection
