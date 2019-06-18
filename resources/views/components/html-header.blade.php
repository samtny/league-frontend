<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Pinball League') }} — @yield('title')</title>

        @section('favicon')
        <!-- Favicon -->
        <!-- TODO: make this dynamic -->
        <link rel="apple-touch-icon" sizes="180x180" href="/storage/favicon/southslope/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/storage/favicon/southslope/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/storage/favicon/southslope/favicon-16x16.png">
        <link rel="manifest" href="/storage/favicon/southslope/site.webmanifest">
        <link rel="mask-icon" href="/storage/favicon/southslope/safari-pinned-tab.svg" color="#5bbad5">
        <link rel="shortcut icon" href="/storage/favicon/southslope/favicon.ico">
        <meta name="msapplication-TileColor" content="#ffc40d">
        <meta name="msapplication-config" content="/storage/favicon/southslope/browserconfig.xml">
        <meta name="theme-color" content="#ebebeb">
        @show

        @if (config('app.env') == 'production')
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ config('services.gtm.id') }}');</script>
        <!-- End Google Tag Manager -->
        @endif

        <!-- Fonts -->
        <link rel="dns-prefetch" href="//fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

        <!-- Styles -->
        <link href="{{ mix('/css/frontend.css') }}" rel="stylesheet">
    </head>
    <body>
        @if (config('app.env') == 'production')
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm.id') }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        @endif
