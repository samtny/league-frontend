@extends('layouts.app')

@section('title', $association->name)

@section('content')
    <div class="title m-b-md">
        <?php echo $association->name; ?>
    </div>
    <div class="message">
        <?php echo $association->name; ?>
    </div>
@endsection
