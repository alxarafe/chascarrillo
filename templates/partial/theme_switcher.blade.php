<div class="dropdown {{ $class ?? '' }}">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle px-3 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-palette me-1"></i> Temas
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
        <li><h6 class="dropdown-header">Elija un estilo:</h6></li>
        <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=chascarrillo">Chascarrillo Oficial</a></li>
        <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=cyberpunk">Cyberpunk 2077</a></li>
        <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=high-contrast">Alta Visibilidad</a></li>
        <li><a class="dropdown-item" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=alternative">Minimalista</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="index.php?module=Chascarrillo&controller=Theme&action=switch&id=chascarrillo"><i class="fas fa-undo me-2"></i>Resetear</a></li>
    </ul>
</div>
