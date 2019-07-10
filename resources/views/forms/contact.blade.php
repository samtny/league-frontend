@extends('layouts.full', ['name' => 'contact'])

@section('title', __('Contact'))

@section('content')
    @component('components/page-title')
        @slot('title')
            {{ __('Contact') }}
        @endslot
    @endcomponent
    <form id="contact" class="contact" method="POST" action="{{ route('contact.submit') }}">
        @csrf
        @honeypot

        <input type="hidden" name="association_id" value="{{ $association->id }}">

        <label for="reason">Reason</label>
        <select id="reason" name="reason">
            <option value="feedback"<?php echo old('reason') == 'feedback' ? 'selected="selected"' : '' ?>>Feedback</option>
            <option value="support"<?php echo old('reason') == 'support' ? 'selected="selected"' : '' ?>>Support</option>
            <option value="register"<?php echo old('reason') == 'register' ? 'selected="selected"' : '' ?>>New Account Registration</option>
        </select>

        <label for="email">Email</label>
        <input class="email @error('email') is-invalid @enderror" name="email" id="email" type="email" placeholder="me@example.com" value="{{ old('email') }}">
        @error('email')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <label for="comment">Comment</label>
        <textarea name="comment" rows="5" maxlength="255" placeholder="Comment">{{ old('comment') }}</textarea>

        <div class="form-actions">
            <input type="submit" class="link-button" value="Submit">
        </div>

    </form>
@endsection
