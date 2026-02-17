@extends('partial.layout.main')

@section('content')
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">Tu contenido, bajo tu control.</h1>
        <p class="hero-subtitle">
            Chascarrillo es un CMS ligero diseñado para quienes aman la simplicidad y el rendimiento.<br>
            <small class="text-muted">Sin cookies, sin rastreo. Solo código limpio y elegancia.</small>
        </p>
        <div class="mt-5">
            <a href="/acerca-de-nosotros" class="btn-alx">Saber más</a>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4 justify-content-center">
        <!-- Simplicidad -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-feather"></i></div>
                <h3 class="feature-title">Simplicidad</h3>
                <p class="feature-description">
                    Una base minimalista diseñada para que entiendas cada línea. Sin complejidad innecesaria ni "magia negra".
                </p>
                <a href="/acerca-de-nosotros" class="btn-outline-alx">Filosofía <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>

        <!-- Markdown -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-code"></i></div>
                <h3 class="feature-title">Markdown Nativo</h3>
                <p class="feature-description">
                    Escribe como un profesional. Gestiona tus posts y páginas desde archivos físicos o desde el panel de control.
                </p>
                <a href="/blog" class="btn-outline-alx">Ver Ejemplos <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>

        <!-- Rendimiento -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-bolt"></i></div>
                <h3 class="feature-title">Rendimiento</h3>
                <p class="feature-description">
                    Construido sobre Alxarafe, ofrece una velocidad de carga instantánea y una arquitectura preparada para el SEO.
                </p>
                <a href="/blog" class="btn-outline-alx">Laboratorio <i class="fas fa-arrow-right ms-2"></i></a>
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
                <a href="/blog" class="btn btn-link text-primary fw-bold text-decoration-none p-0">
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
                                <a href="/blog/{{ $post->slug }}" class="text-decoration-none fw-bold small">
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
