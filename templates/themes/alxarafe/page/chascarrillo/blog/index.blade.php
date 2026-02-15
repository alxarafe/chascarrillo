@extends('partial.layout.main')

@section('content')
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">Alxarafe: Ingeniería, IA y... ¡Cimientos!</h1>
        <p class="hero-subtitle">
            Un espacio dedicado a la creación de software robusto, la mentoría sénior y el control humano sobre la Inteligencia Artificial.<br>
            <small class="text-muted">Sin cookies, sin rastreo, sin ruido. Sólo arquitectura limpia en PHP 8.5.</small>
        </p>
        <div class="mt-5">
            <a href="index.php?module=Chascarrillo&controller=Blog&action=show&slug=manifiesto-ia" class="btn-alx">Manifiesto IA</a>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4 justify-content-center">
        <!-- El framework -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-bullseye"></i></div>
                <h3 class="feature-title">El framework</h3>
                <p class="feature-description">
                    Una base minimalista diseñada para el control total. Sin «magia negra», priorizando la trazabilidad y los estándares PSR.
                </p>
                <a href="https://alxarafe.com" target="_blank" class="btn-outline-alx">Framework <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>

        <!-- Manifiesto IA -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-brain"></i></div>
                <h3 class="feature-title">Manifiesto IA</h3>
                <p class="feature-description">
                    La IA es el obrero, no el arquitecto. Mi enfoque para integrar la generación de código sin comprometer la escalabilidad.
                </p>
                <a href="index.php?module=Chascarrillo&controller=Blog&action=show&slug=manifiesto-ia" class="btn-outline-alx">Manifiesto IA <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>

        <!-- El laboratorio -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-flask"></i></div>
                <h3 class="feature-title">El laboratorio</h3>
                <p class="feature-description">
                    Documentando el proceso de formación de la próxima generación de desarrolladores. Casos reales, errores y soluciones en el mundo PHP.
                </p>
                @if(!$posts->isEmpty())
                    <a href="?module=Chascarrillo&controller=Blog&action=index" class="btn-outline-alx">Blog <i class="fas fa-arrow-right ms-2"></i></a>
                @else
                    <a href="#" class="btn-outline-alx">Próximamente <i class="fas fa-arrow-right ms-2"></i></a>
                @endif
            </div>
        </div>
    </div>

    @if(!$posts->isEmpty())
        <div class="mt-5 pt-5 border-top">
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <h2 class="h1 fw-800 text-secondary mb-0">Últimos Chascarrillos</h2>
                    <p class="text-muted">Novedades desde el laboratorio de Alxarafe</p>
                </div>
                <a href="?module=Chascarrillo&controller=Blog&action=index" class="btn btn-link text-primary fw-bold text-decoration-none p-0">
                    Ver todos <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="row g-4">
                @foreach($posts->take(3) as $post)
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden card-hover">
                            <div class="card-body p-4">
                                <span class="badge bg-primary-soft text-primary mb-3">{{ $post->published_at ? $post->published_at->format('d M, Y') : 'Borrador' }}</span>
                                <h4 class="card-title fw-bold mb-3 h5">{{ $post->title }}</h4>
                                <p class="card-text text-muted small mb-4">{{ $post->getExcerpt(140) }}</p>
                                <a href="?module=Chascarrillo&controller=Blog&action=show&slug={{ $post->slug }}" class="text-decoration-none fw-bold small">
                                    Seguir leyendo <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
