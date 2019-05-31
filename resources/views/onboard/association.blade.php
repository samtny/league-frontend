@extends('layouts.app')

@section('title', 'Onboard Association')

@section('content')
    <div class="title m-b-md">
        Create Association - Success!
    </div>
    <div class="message">
        Successfully created association "<?php echo $association->name; ?>"
    </div>
@endsection
