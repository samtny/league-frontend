@extends('layouts.admin')

@section('title', 'Create Association')

@section('breadcrumb')
    {{ Breadcrumbs::render('association.create') }}
@endsection

@section('content')
    <div class="title m-b-md">
        Create Association
    </div>
    <div class="form">
        <form method="POST" action="{{ route('association.create') }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="mb-3">
                <label for="user_id">Owner</label>
                <select class="form-control" id="user_id" name="user_id">
                    <option value="<?php echo($current_user->id); ?>"><?php echo($current_user->name); ?></option>
                </select>
            </div>

            <div class="mb-3">
                <label for="name">Name</label>
                <input class="form-control" id="name" type="text" name="name" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-item">
                    <input class="form-control" id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
