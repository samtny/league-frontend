@extends('layouts.full', ['name' => 'denied'])

@section('title', 'Access Denied')

@section('content')
    <div class="title m-b-md">
        Access Denied
    </div>

    <div class="message">
        You do not have permission to access that resource.
    </div>
@endsection
