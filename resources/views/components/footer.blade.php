<footer>
    <span class="divider"></span>
    @section('footer-menu')
        <nav class="footer-menu">
            <ul>
                <li>
                    <a href="{{ route('association.venues.directory') }}">{{ $association->venues_label_override ?: 'Venues' }}</a>
                </li>
                <li>
                    <a href="{{ route('association.roster') }}">Roster</a>
                </li>
                <li>
                    <a href="{{ route('association.rules') }}">Rules</a>
                </li>

                @guest
                    <li>
                        <a href="{{ route('login') }}">Login</a>
                    </li>
                @else
                    @can('view-admin-pages')
                        <li>
                            <a href="{{ route('association.view', ['association' => $association]) }}">Admin</a>
                        </li>
                    @else
                        <li>
                            <a href="{{ route('logout') }}"
                                onclick="event.preventDefault();
                                                document.getElementById('logout-form').submit();">
                                Logout
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                @csrf
                            </form>
                        </li>
                    @endcan
                @endguest

            </ul>
        </nav>
    @show
    @section('footer-copyright')
        <div class="footer-copyright">
            @if (@isset($subdomain) && $subdomain != 'pinballnyc')
            <span class="copyright">© <?php echo date('Y'); ?></span>&nbsp;<span class="attribution">pinballleague.org</span>
            @endif
        </div>
    @show
</header>
