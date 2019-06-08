@extends('layouts.full', ['name' => 'home'])

@section('content')
    @component('page-title')
        @slot('title')
            Pinball League
        @endslot
    @endcomponent
@endsection
