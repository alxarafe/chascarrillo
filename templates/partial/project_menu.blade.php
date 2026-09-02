@php
    $config = \Alxarafe\Infrastructure\Persistence\Config::getConfig();
    $blogEnabled = filter_var($config->blog->enabled ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

    // Get dynamic menu
    $headMenu = \Modules\Chascarrillo\Model\Menu::getBySlug('header-menu');
    $headMenuItems = $headMenu ? ($headMenu->items ?? collect()) : collect();

    if (!$blogEnabled) {
        $headMenuItems = $headMenuItems->filter(function ($item) {
            $url = (string) ($item->url ?? '');
            return !str_starts_with($url, '/blog') && !str_contains($url, 'controller=Blog');
        });
    }
@endphp

<nav class="alx-navbar d-flex align-items-center" aria-label="Menú principal">
    <a class="alx-navbar-brand d-flex align-items-center text-decoration-none fw-bold" href="/">
        <i class="fas fa-cubes me-2"></i>
        <span>{{ $config->main->appName ?? 'Chascarrillo' }}</span>
    </a>

    <ul class="alx-navbar-nav d-none d-md-flex list-unstyled mb-0 gap-1 ms-3">
        @foreach($headMenuItems as $item)
            @if($item->children && $item->children->count() > 0)
                <li class="nav-item dropdown">
                    <a class="alx-nav-link dropdown-toggle px-2 py-1 rounded" href="{{ $item->url ?? '#' }}" id="navDrop{{ $item->id }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        @if($item->icon)<i class="{{ $item->icon }} me-1"></i>@endif {{ $item->label }}
                    </a>
                    <ul class="dropdown-menu shadow border-0" aria-labelledby="navDrop{{ $item->id }}">
                        @foreach($item->children as $child)
                            <li><a class="dropdown-item" href="{{ $child->url }}" target="{{ $child->target ?? '_self' }}">{{ $child->label }}</a></li>
                        @endforeach
                    </ul>
                </li>
            @else
                <li class="nav-item">
                    <a class="alx-nav-link px-2 py-1 rounded" href="{{ $item->url ?? '#' }}" target="{{ $item->target ?? '_self' }}">
                        @if($item->icon)<i class="{{ $item->icon }} me-1"></i>@endif {{ $item->label }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
