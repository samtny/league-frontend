@extends('layouts.full', ['name' => 'about'])

@section('title', 'About')

@section('favicon')
    @parent
@endsection

@section('content')
    @component('components/page-title')
        @slot('title')
            About
        @endslot
    @endcomponent
    <div class="richtext">
        <p>
            Manage schedules, teams, venues, divisions, score submissions and more for leagues of any size.
        </p>
        <p>
            Interested? Hit us up on our <a href="/contact">Contact</a> page.
        </p>
        <p>
            Source code on <a href="https://github.com/samtny/league-frontend" target="_blank">Github</a>.
        </p>
    </div>
@endsection
