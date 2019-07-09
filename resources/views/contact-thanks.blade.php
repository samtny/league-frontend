@extends('layouts.full', ['name' => 'thanks'])

@section('title', 'Thanks')

@section('favicon')
    @parent
@endsection

@section('content')
    @component('components/page-title')
        @slot('title')
            Thank You!
        @endslot
    @endcomponent
    <div class="richtext">
        <p>Thank you for contacting us, we will be in touch shortly.</p>
    </div>
@endsection
