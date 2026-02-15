@php
    $companyTz = \Alxarafe\Base\Config::getConfig()->main->timezone ?? 'UTC';
    $userTz = (\Alxarafe\Lib\Auth::$user->timezone ?? null) ?: $companyTz;
@endphp

<header class="alx-navbar">
    <div class="container d-flex align-items-center justify-content-between">
        
        <!-- Brand -->
        <a href="index.php" class="d-flex align-items-center text-decoration-none">
            <span class="fs-4 fw-800 text-secondary" style="letter-spacing: -0.02em;">Alxarafe</span>
        </a>

        <!-- Horizontal Menu -->
        <nav class="d-none d-lg-flex align-items-center gap-4">
            <a href="index.php" class="nav-link-alx p-0">Inicio</a>
            
            {{-- Páginas fijas de la Base de Datos --}}
            @foreach(\Modules\Chascarrillo\Model\Post::getMenuPages() as $page)
                <a href="index.php?module=Chascarrillo&controller=Page&action=show&slug={{ $page->slug }}" class="nav-link-alx p-0">{{ $page->title }}</a>
            @endforeach

            @if(!empty($main_menu) && is_array($main_menu))
                @foreach($main_menu as $item)
                    <a href="{{ $item['url'] }}" class="nav-link-alx p-0">{{ $item['label'] }}</a>
                @endforeach
            @endif
            <a href="https://docs.alxarafe.com" target="_blank" class="nav-link-alx p-0">Documentación <i class="fas fa-external-link-alt small ms-1" style="font-size: 0.7em;"></i></a>
        </nav>

        <!-- Social & Tools -->
        <div class="d-flex align-items-center gap-4">
            <div class="d-flex align-items-center gap-3">
                <a href="https://www.linkedin.com/in/rsanjose/" target="_blank" class="social-link-alx" title="LinkedIn">
                    <i class="fab fa-linkedin"></i>
                </a>
                <a href="https://github.com/alxarafe/alxarafe" target="_blank" class="social-link-alx" title="GitHub">
                    <i class="fab fa-github"></i>
                </a>
            </div>
            
            <div class="vr d-none d-md-block text-gray-300" style="height: 24px;"></div>

            {{-- Theme Switcher --}}
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle px-3 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-palette me-1"></i> Temas
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><h6 class="dropdown-header">Elija un estilo:</h6></li>
                    <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=alxarafe">Moderno (Alxarafe)</a></li>
                    <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=cyberpunk">Cyberpunk 2077</a></li>
                    <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=high-contrast">Alta Visibilidad</a></li>
                    <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=alternative">Minimalista</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=alxarafe"><i class="fas fa-undo me-2"></i>Resetear</a></li>
                </ul>
            </div>

            <div class="vr d-none d-md-block text-gray-300" style="height: 24px;"></div>

            {{-- Clock --}}
            <div class="d-none d-xl-flex align-items-center text-muted small">
                <i class="far fa-clock me-2"></i>
                <span id="clock-display" style="font-variant-numeric: tabular-nums;">--:--:--</span>
            </div>

            {{-- User Dropdown --}}
            @if(\Alxarafe\Lib\Auth::$user)
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center text-dark p-0" href="#" id="navbarUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        @if(!empty(\Alxarafe\Lib\Auth::$user->avatar) && file_exists(\Alxarafe\Base\Config::getPublicRoot() . '/' . \Alxarafe\Lib\Auth::$user->avatar))
                            <img src="{{ \Alxarafe\Lib\Auth::$user->avatar }}" class="rounded-circle border me-2" style="width: 28px; height: 28px; object-fit: cover;">
                        @else
                            <i class="fas fa-user-circle fa-lg text-secondary me-2"></i>
                        @endif
                        <span class="d-none d-sm-inline small fw-bold">{{ \Alxarafe\Lib\Auth::$user->username }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="navbarUser">
                        <li><a class="dropdown-item" href="index.php?module=Admin&controller=Auth&action=logout"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            @else
                <a href="index.php?module=Admin&controller=Auth" class="btn btn-sm btn-primary px-3 rounded-pill">Login</a>
            @endif
            
            {{-- Mobile Menu Toggle --}}
            <button class="btn d-lg-none p-1" type="button" data-bs-toggle="collapse" data-bs-target="#mobileMenu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    {{-- Mobile Menu Collapse --}}
    <div class="collapse d-lg-none border-top mt-3" id="mobileMenu">
        <div class="container py-3">
            <nav class="nav flex-column gap-3">
                <a href="index.php" class="nav-link-alx p-0">Inicio</a>
                
                @foreach(\Modules\Chascarrillo\Model\Post::getMenuPages() as $page)
                    <a href="index.php?module=Chascarrillo&controller=Page&action=show&slug={{ $page->slug }}" class="nav-link-alx p-0">{{ $page->title }}</a>
                @endforeach

                @if(!empty($main_menu) && is_array($main_menu))
                    @foreach($main_menu as $item)
                        <a href="{{ $item['url'] }}" class="nav-link-alx p-0">{{ $item['label'] }}</a>
                    @endforeach
                @endif
                <a href="https://docs.alxarafe.com" target="_blank" class="nav-link-alx p-0">Documentación</a>
                <div class="d-flex gap-3 pt-2">
                    <a href="https://www.linkedin.com/in/rsanjose/" target="_blank" class="social-link-alx"><i class="fab fa-linkedin"></i></a>
                    <a href="https://github.com/alxarafe/alxarafe" target="_blank" class="social-link-alx"><i class="fab fa-github"></i></a>
                </div>
                <div class="pt-3 border-top">
                    <p class="small text-muted mb-2">Cambiar tema:</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=alxarafe" class="btn btn-xs btn-outline-secondary rounded-pill">Alxarafe</a>
                        <a href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=cyberpunk" class="btn btn-xs btn-outline-secondary rounded-pill">Cyberpunk</a>
                        <a href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=high-contrast" class="btn btn-xs btn-outline-secondary rounded-pill">Contraste</a>
                        <a href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=alternative" class="btn btn-xs btn-outline-secondary rounded-pill">Minimal</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fmt = (tz) => new Date().toLocaleString('es-ES', { timeZone: tz, hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        function updateClock() {
            const el = document.getElementById('clock-display');
            if(el) el.innerText = fmt('{{ $userTz }}');
        }
        setInterval(updateClock, 1000);
        updateClock();
    });
</script>