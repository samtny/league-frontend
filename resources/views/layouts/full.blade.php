@component('components/html-header')
@endcomponent
<body>
    @component('components/header')
    @endcomponent
    @component('layout', ['name' => 'full'])
        @component('template', ['name' => $name ])
            <div class="content">
                @component('components/alert')
                @endcomponent

                @yield('content')
            </div>
        @endcomponent
    @endcomponent
    @component('components/footer')
    @endcomponent
</body>
@component('components/html-footer')
@endcomponent
