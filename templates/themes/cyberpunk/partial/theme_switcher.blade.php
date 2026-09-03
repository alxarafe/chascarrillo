@php
    $themes = \Alxarafe\Infrastructure\Lib\Functions::getThemes();
    $currentTheme = $_SESSION['alx_theme_test']
        ?? $_COOKIE['alx_theme']
        ?? \Alxarafe\Infrastructure\Persistence\Config::getConfig()->main->theme
        ?? 'default';
@endphp

<a class="cyber-retro-button" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ \Alxarafe\Infrastructure\Lib\Trans::_('select_theme') }}">
    <div class="cyber-button-inner">
        <i class="cyber-retro-icon fas fa-palette fa-2x cyber-icon"></i>
    </div>
    <span class="cyber-pixel pixel-tl"></span>
    <span class="cyber-pixel pixel-tr"></span>
    <span class="cyber-pixel pixel-bl"></span>
    <span class="cyber-pixel pixel-br"></span>
</a>

<ul class="dropdown-menu dropdown-menu-end shadow animate__animated animate__fadeInFast">
    <li class="dropdown-header text-uppercase small fw-bold text-primary">{{ \Alxarafe\Infrastructure\Lib\Trans::_('available_themes') }}</li>
    <li><hr class="dropdown-divider"></li>
    @foreach($themes as $name => $label)
        <li>
            <a class="dropdown-item d-flex align-items-center {{ $currentTheme === $name ? 'active' : '' }}"
               href="/index.php?module=Chascarrillo&controller=Theme&action=switch&id={{ $name }}">
                <i class="fas fa-circle me-2" style="font-size: 0.5em;"></i>
                {{ $label }}
            </a>
        </li>
    @endforeach
</ul>
