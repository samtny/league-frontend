@extends('layouts.admin')

@section('title', 'Edit Member')

@section('breadcrumb')
    {{ Breadcrumbs::render('member.edit', $member) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col"><?php echo $member->name; ?></h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('member.update', ['association' => $member->association, 'member' => $member])}}">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $member->name) }}" maxlength="128">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select class="form-control" id="role" name="role">
                    <?php foreach (['Player', 'Captain', 'Reserve'] as $role): ?>
                    <option value="<?php echo $role; ?>"<?php echo $member->role == $role ? ' selected' : ''; ?>><?php echo $role; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input class="btn btn-primary" id="submit" type="submit" value="Update"/>
                </div>
                <div class="form-group">
                    <a class="btn btn-warning" href="{{ route('member.deleteConfirm', ['association' => $member->association, 'member' => $member]) }}">Delete Member</a>
                </div>
            </div>

        </form>
    </div>
@endsection
