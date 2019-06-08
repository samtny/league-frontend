@extends('layouts.admin')

@section('title', $series->name)

@section('content')
    <div class="title m-b-md">
        <?php echo $series->name; ?>
    </div>
    <div class="message">
        <?php echo $series->name; ?>
    </div>
@endsection
