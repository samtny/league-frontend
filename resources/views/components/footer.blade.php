<footer>
    <span class="divider"></span>
    @section('footer-menu')
        <nav class="footer-menu">
            <ul>
                <li>
                    <a href="{{ route('about') }}">About</a>
                </li>
                <li>
                    <a href="{{ route('contact') }}">Contact</a>
                </li>


                 <!-- Authentication Links -->
                 @guest

                 <li>
                    <a href="{{ route('login') }}">Login</a>
                </li>

                 <?php



                 /*
                 <li>
                     <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                 </li>
                     @if (Route::has('register'))
                     <li>
                         <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                     </li>
                     @endif
                 */ ?>
                 @else
                     <?php /*
                     <li>
                         <a id="navbarDropdown" class="nav-link dropdown-toggle" href="{{ route('user', [ 'id' => Auth::user()->id ]) }}" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                             {{ Auth::user()->name }} <span class="caret"></span>
                         </a>
                     </li>
                     */ ?>
                     @can('view-admin-pages')
                     <li>
                         <a id="navbarDropdown" class="nav-link dropdown-toggle" href="{{ route('admin') }}" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                             {{ __('Admin') }}
                         </a>
                     </li>
                     @endcan
                     <li>
                         <a class="dropdown-item" href="{{ route('logout') }}"
                             onclick="event.preventDefault();
                                             document.getElementById('logout-form').submit();">
                             {{ __('Logout') }}
                         </a>
                         <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                             @csrf
                         </form>
                     </li>
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
