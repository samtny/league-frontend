@extends('layouts.admin')

@section('title', 'Create Series')

@section('content')
    <div class="title m-b-md">
        Create Series
    </div>
    <div class="form">
        <form method="POST" action="/series/create">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-item">
                <label for="user_id">Owner</label>
                <select id="user_id" name="user_id">
                    <option value="<?php echo($current_user->id); ?>"><?php echo($current_user->name); ?></option>
                </select>
            </div>

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-item">
                <label for="association_id">Association</label>
                <select id="association_id" name="association_id">
                    <option value="">- No Association -</option>
                    <?php foreach($associations as $association): ?>
                        <option value="<?php echo $association->id; ?>"><?php echo $association->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
