<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Pinball League') }} — @yield('title')</title>

        <!-- Fonts -->
        <link rel="dns-prefetch" href="//fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

        <!-- Styles -->
        <link href="/css/laravel.css" rel="stylesheet">
        <link href="/css/association/{{ $association->subdomain }}.css" rel="stylesheet">
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            @section('links')
                <div class="top-right links">
                    <a href="{{ url('/') }}">Home</a>
                    <a href="{{ route('standings') }}">Standings</a>
                    <a href="{{ route('schedule') }}">Schedule</a>
                    <!-- Authentication Links -->
                    @guest

                        <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                        @if (Route::has('register'))
                            <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                        @endif
                    @else
                        <a id="navbarDropdown" class="nav-link dropdown-toggle" href="{{ route('user', [ 'id' => Auth::user()->id ]) }}" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                            {{ Auth::user()->name }} <span class="caret"></span>
                        </a>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                            onclick="event.preventDefault();
                                            document.getElementById('logout-form').submit();">
                            {{ __('Logout') }}
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    @endguest
                </div>
            @show

            <div class="content">
                @if (session('message'))
                    <div class="alert alert-message" role="alert">
                        {{ session('message') }}
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </body>
</html>
