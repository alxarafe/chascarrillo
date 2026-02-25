<div class="dropdown {{ $class ?? '' }}">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle px-3 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-palette me-1"></i> {{ \Alxarafe\Lib\Trans::_('themes') }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
        <li><h6 class="dropdown-header">{{ \Alxarafe\Lib\Trans::_('choose_a_style') }}</h6></li>
        @foreach(\Alxarafe\Lib\Functions::getThemes() as $themeId => $themeName)
            <li><a class="dropdown-item" href="/index.php?module=Chascarrillo&controller=Theme&action=switch&id={{ $themeId }}">{{ $themeName }}</a></li>
        @endforeach
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="/index.php?module=Chascarrillo&controller=Theme&action=switch&id=chascarrillo"><i class="fas fa-undo me-2"></i>Reset</a></li>
    </ul>
</div>

