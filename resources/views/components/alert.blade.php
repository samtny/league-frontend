@if (session('message'))
    <div class="alert alert-message" role="alert">
        {{ session('message') }}
    </div>
@endif
