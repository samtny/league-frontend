@extends('layouts.app')

@section('title', 'Create Schedule')

@section('content')
    <div class="title m-b-md">
        Create Schedule
    </div>
    <div class="form">
        <form method="POST" action="/series/create">
            @csrf

            <div class="form-item">
                <label for="series_id">Series</label>
                <select id="series_id" name="series_id">
                    <option value="">- No series -</option>
                    <?php foreach($available_series as $item): ?>
                        <option value="<?php echo $item->id; ?>"<?php if($item->id === $series->id) echo ' selected'; ?>><?php echo $item->name; ?></option>
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
