<footer>
    @section('footer-menu')
        <nav class="footer-menu">
            <ul>
                <li>
                    <a href="{{ route('about') }}">About</a>
                </li>
                <li>
                    <a href="{{ route('contact') }}">Contact</a>
                </li>
            </ul>
        </nav>
    @show
    @section('footer-copyright')
        <div class="footer-copyright">
            <span class="copyright">© <?php echo date('Y'); ?></span>&nbsp;<span class="attribution">pinballleague.org</span>
        </div>
    @show
</header>
