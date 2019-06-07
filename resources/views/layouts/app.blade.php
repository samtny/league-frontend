@component('components/html-header')
@endcomponent
    <body>
        @component('components/header')

        @endcomponent
        <div class="content">
            @component('components/alert')
            @endcomponent

            @yield('content')
        </div>
    </body>
@component('components/html-footer')
@endcomponent
