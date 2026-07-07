@extends('layouts.admin')

@section('title', 'Add Member')

@section('breadcrumb')
    {{ Breadcrumbs::render('member.create', $team) }}
@endsection

@section('content')
    <h1>
        Add Member
    </h1>
    <div class="form">
        <form method="POST" action="{{ route('member.create', ['association' => $association, 'team' => $team]) }}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" maxlength="128">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Member</small>
            </div>

            <div class="mb-3">
                <label for="role">Role</label>
                <select class="form-control" id="role" name="role">
                    <?php foreach (['Player', 'Captain', 'Reserve'] as $role): ?>
                    <option value="<?php echo $role; ?>"<?php echo old('role', 'Player') == $role ? ' selected' : ''; ?>><?php echo $role; ?></option>
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
