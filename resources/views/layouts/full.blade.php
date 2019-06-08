@component('components/html-header')
@endcomponent
<body>
    @component('components/header')
    @endcomponent
    @component('layouts/base', ['name' => 'full'])
        <div class="t--template t--{{ $name }}">
            <div class="content">
                @component('components/alert')
                @endcomponent

                @yield('content')
            </div>
        </div>
    @endcomponent
    @component('components/footer')
    @endcomponent
</body>
@component('components/html-footer')
@endcomponent
