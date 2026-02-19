@php
    $config = \Alxarafe\Base\Config::getConfig();
    $companyTz = $config->main->timezone ?? 'UTC';
    $userTz = (\Alxarafe\Lib\Auth::$user->timezone ?? null) ?: $companyTz;
    $currentTheme = $config->main->theme ?? 'default';
    
    // Social links from config
    $githubUrl = $config->social->github ?? 'https://github.com/alxarafe/alxarafe';
    $linkedinUrl = $config->social->linkedin ?? 'https://www.linkedin.com/in/rsanjose/';

    // Get menu pages dynamically (legacy / simple pages)
    // $menuPages = \Modules\Chascarrillo\Model\Post::getMenuPages();

    // Get dynamic menu
    $headMenu = \Modules\Chascarrillo\Model\Menu::getBySlug('header-menu');
    $menuItems = $headMenu ? $headMenu->items : collect();

    // Security check: Default password alert
    $showPasswordWarning = false;
    if (\Alxarafe\Lib\Auth::$user && \Alxarafe\Lib\Auth::$user->is_admin) {
        $showPasswordWarning = password_verify('password', \Alxarafe\Lib\Auth::$user->password);
    }
@endphp

@if($showPasswordWarning)
    <div class="alert alert-warning border-0 rounded-0 m-0 py-2 text-center" style="background: #ffc107; color: #000; font-size: 0.85em;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>{{ \Alxarafe\Lib\Trans::_('security') }}:</strong> {{ \Alxarafe\Lib\Trans::_('default_password_warning') }} <a href="index.php?module=Admin&controller=Profile" class="fw-bold text-dark text-decoration-underline">{{ \Alxarafe\Lib\Trans::_('change_it_now') }}</a>.
    </div>
@endif

<header class="app-header navbar navbar-expand-lg">
    <div class="container">
        <!-- Brand / Logo -->
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <span class="brand-text">{{ $config->main->appName ?? 'Chascarrillo' }}</span>
        </a>

        <!-- Toggler for mobile -->
        <button class="navbar-toggler border-0 p-2" type="button" data-bs-toggle="collapse" data-bs-target="#appNavigation" aria-controls="appNavigation" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation Links (Centered as in alxarafe.es) -->
        <div class="collapse navbar-collapse" id="appNavigation">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-2 ms-lg-4">
                {{-- Dynamic menu items from database --}}
                @foreach($menuItems as $item)
                    @if($item->children->count() > 0)
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="{{ $item->url ?? '#' }}" id="navDrop{{ $item->id }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                @if($item->icon)<i class="{{ $item->icon }} me-1"></i>@endif {{ $item->label }}
                            </a>
                            <ul class="dropdown-menu shadow border-0" aria-labelledby="navDrop{{ $item->id }}">
                                @foreach($item->children as $child)
                                    <li><a class="dropdown-item" href="{{ $child->url }}" target="{{ $child->target }}">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ $item->url }}" target="{{ $item->target }}">
                                @if($item->icon)<i class="{{ $item->icon }} me-1"></i>@endif {{ $item->label }}
                            </a>
                        </li>
                    @endif
                @endforeach
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="docsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ \Alxarafe\Lib\Trans::_('documentation') }}
                    </a>
                    <ul class="dropdown-menu shadow border-0" aria-labelledby="docsDropdown">
                        <li><a class="dropdown-item" href="https://docs.alxarafe.com/es" target="_blank">{{ \Alxarafe\Lib\Trans::_('spanish') ?? 'Español' }}</a></li>
                        <li><a class="dropdown-item" href="https://docs.alxarafe.com/en" target="_blank">{{ \Alxarafe\Lib\Trans::_('english') ?? 'Inglés' }}</a></li>
                    </ul>
                </li>
            </ul>

            <!-- Right Side Tools -->
            <div class="header-tools d-flex align-items-center gap-2 gap-xl-3 mt-3 mt-lg-0">
                
                <div class="d-none d-xxl-flex gap-3 me-2">
                    <a href="{{ $githubUrl }}" target="_blank" class="text-secondary small" title="GitHub"><i class="fab fa-github"></i></a>
                    <a href="{{ $linkedinUrl }}" target="_blank" class="text-secondary small" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                </div>

                @include('partial/theme_switcher')

                <div class="vr d-none d-lg-block text-gray-300" style="height: 20px;"></div>

                @if(\Alxarafe\Lib\Auth::$user)
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 p-0" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            @if(!empty(\Alxarafe\Lib\Auth::$user->avatar) && file_exists(\Alxarafe\Base\Config::getPublicRoot() . '/' . \Alxarafe\Lib\Auth::$user->avatar))
                                <img src="{{ \Alxarafe\Lib\Auth::$user->avatar }}" class="rounded-circle border" style="width: 24px; height: 24px; object-fit: cover;">
                            @else
                                <i class="fas fa-user-circle text-secondary fa-lg"></i>
                            @endif
                            <span class="small fw-bold d-none d-sm-inline">{{ \Alxarafe\Lib\Auth::$user->username }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userMenu">
                            <li><a class="dropdown-item" href="index.php?module=Admin&controller=Dashboard"><i class="fas fa-cog me-2"></i> Dashboard Admin</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="index.php?module=Admin&controller=Auth&action=logout"><i class="fas fa-sign-out-alt me-2"></i> {{ \Alxarafe\Lib\Trans::_('logout') }}</a></li>
                        </ul>
                    </div>
                @else
                    <a href="index.php?module=Admin&controller=Auth" class="btn btn-sm btn-outline-primary px-3 rounded-pill" title="Acceso Usuarios">
                        <i class="fas fa-lock small"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>
</header>
