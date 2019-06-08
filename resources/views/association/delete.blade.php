@extends('layouts.admin')

@section('title', 'Delete ' . $association->name . '?')

@section('content')
    <div class="title m-b-md">
        <?php echo 'Delete ' . $association->name . '?'; ?>
    </div>
    <div class="links">
        <a href="{{ route('association.view', [ 'association' => $association ]) }}">Cancel</a>
    </div>
    <div class="form">
        <form method="POST" action="/association/<?php echo $association->id; ?>/delete">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Yes"/>
                </div>
            </div>

        </form>
    </div>
@endsection
