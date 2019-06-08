@extends('layouts.full', ['name' => 'home'])

@section('content')
    @component('components/page-title')
        @slot('title')
            Pinball League
        @endslot
    @endcomponent
@endsection
