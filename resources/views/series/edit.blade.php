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
        <form method="POST" action="{{ route('series.update', ['series' => $series ]) }}">
            @csrf

            <input type="hidden" name="id" value="{{ $series->id }}">

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $series->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input class="form-control" id="start_date" type="date" name="start_date" value="{{ old('start_date', $start_date_string) }}">
            </div>

            <div class="form-group">
                <label for="end_date">End Date</label>
                <input class="form-control" id="end_date" type="date" name="end_date" value="{{ old('end_date', $end_date_string) }}">
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <button class="btn btn-primary" id="submit" type="submit">Update</button>
                </div>
                <div class="form-group">
                    <a class="btn btn-warning" href="{{ route('series.delete', [ 'series' => $series ]) }}">Delete Series</a>
                </div>
            </div>
        </form>
    </div>

@endsection
