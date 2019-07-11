<header>
    @section('main-menu')
        <nav class="main-menu">
            <ul>
                <li>
                    <a href="{{ url('/') }}">Home</a>
                </li>
                <li>
                    <a href="{{ route('association.standings') }}">Standings</a>
                </li>
                <li>
                    <a href="{{ route('association.schedule') }}">Schedule</a>
                </li>

            </ul>
        </nav>
    @show
</header>
