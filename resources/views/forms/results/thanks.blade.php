@extends('layouts.full', ['name' => 'scores'])

@section('title', __('Thanks'))

@section('content')
    @component('components/page-title')
        @slot('title')
            <?php echo __('Thanks!'); ?>
        @endslot
    @endcomponent
    <div class="message">
        Your score was submitted.
    </div>
@endsection
