@extends('layouts.admin')

@section('title', 'Create Series')

@section('breadcrumb')
    {{ Breadcrumbs::render('series.create') }}
@endsection

@section('content')
    <h1>
        Create Series
    </h1>
    <div class="form">
        <form method="POST" action="{{ route('series.create') }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="mb-3">
                <label for="user_id">Owner</label>
                <select id="user_id" class="form-control" name="user_id">
                    <option value="<?php echo($current_user->id); ?>"><?php echo($current_user->name); ?></option>
                </select>
            </div>

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="association_id">Association</label>
                <select id="association_id" class="form-control" name="association_id">
                    <option value="">- No Association -</option>
                    <?php foreach($associations as $association): ?>
                        <option value="<?php echo $association->id; ?>"><?php echo $association->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
