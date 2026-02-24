<div class="sidebar" id="sidebar-wrapper">
    <!-- Admin Context Info -->
    <div class="sidebar-heading border-bottom px-4 py-4">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="fas fa-tools"></i>
            </div>
            <div>
                <div class="fw-bold small text-uppercase letter-spacing-wider" style="letter-spacing: 0.1em; font-size: 0.7rem; color: var(--alx-muted);">Administración</div>
                <div class="fw-800" style="color: var(--alx-secondary); font-size: 0.9rem;">Dashboard</div>
            </div>
        </div>
    </div>

    <!-- Main Admin Menu (Navigation) -->
    <div class="py-3">
        @if(!empty($main_menu) && is_array($main_menu))
            <nav class="nav flex-column gap-1">
                @foreach($main_menu as $item)
                    <a href="{{ $item['url'] }}" class="nav-link px-4 py-2 d-flex align-items-center gap-3 {{ (strpos($_SERVER['REQUEST_URI'], $item['url']) !== false) ? 'active' : '' }}" title="{{ $item['label'] }}">
                        <i class="{{ $item['icon'] ?? 'fas fa-circle' }} text-muted" style="width: 20px;"></i>
                        <span class="fw-600" style="font-size: 0.9rem;">{{ $item['label'] }}</span>
                        @if(!empty($item['badge']))
                            <span class="badge rounded-pill bg-danger ms-auto" style="font-size: 0.65rem;">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif
    </div>

    <div class="mt-auto border-top p-4">
        <a href="/index.php?module=Admin&controller=Auth&action=logout" class="btn btn-sm btn-outline-danger w-100 rounded-pill">
            <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
        </a>
    </div>
</div>

<style>
    .sidebar .nav-link {
        color: var(--alx-text);
        transition: all 0.2s;
    }
    .sidebar .nav-link:hover {
        background: rgba(34, 53, 221, 0.05);
        color: var(--alx-primary);
        padding-left: 1.75rem !important;
    }
    .sidebar .nav-link.active {
        color: var(--alx-primary);
        background: rgba(34, 53, 221, 0.08);
        border-right: 3px solid var(--alx-primary);
    }
</style>
