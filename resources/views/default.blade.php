@extends('layouts.default', ['name' => 'default'])

@section('content')
    @component('components/page-title')
        @slot('title')
            Pinball League
        @endslot
    @endcomponent
@endsection
