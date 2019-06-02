@extends('layouts.app')

@section('title', 'Onboard Series')

@section('content')
    <div class="title m-b-md">
        Create Series - Success!
    </div>
    <div class="message">
        Successfully created series "<?php echo $series->name; ?>"
    </div>
@endsection
