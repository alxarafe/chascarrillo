<div class="dropdown {{ $class ?? '' }}">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle px-3 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-palette me-1"></i> {{ \Alxarafe\Lib\Trans::_('themes') }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
        <li><h6 class="dropdown-header">Choose a style:</h6></li>
        <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=chascarrillo">Official Chascarrillo</a></li>
        <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=cyberpunk">Cyberpunk 2077</a></li>
        <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=high-contrast">High Contrast</a></li>
        <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=alternative">Minimalist</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=chascarrillo"><i class="fas fa-undo me-2"></i>Reset</a></li>
    </ul>
</div>
