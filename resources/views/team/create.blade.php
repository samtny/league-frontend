@extends('layouts.app')

@section('title', 'Create Team')

@section('content')
    <div class="title m-b-md">
        Create Team
    </div>
    <div class="form">
        <form method="POST" action="/association/<?php echo($association->id) ?>/team/create">
            @csrf

            <input type="hidden" name="url" value="{{  URL::previous()  }}">

            <input type="hidden" name="association_id" value="<?php echo($association->id); ?>">

            <div class="form-item">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" class="@error('name') is-invalid @enderror">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <span class="form-item-help">Enter a name for this Team</span>
            </div>

            <div class="form-item">
                <label for="venue_id">Home Venue</label>
                <select id="venue_id" name="venue_id">
                    <option value="">- No Venue -</option>
                    <?php foreach($association->venues->sortBy('name') as $venue): ?>
                    <option value="<?php echo $venue->id; ?>"><?php echo $venue->name; ?></option>
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
