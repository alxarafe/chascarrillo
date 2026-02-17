@extends('partial.layout.main')

@section('content')
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">{{ \Alxarafe\Lib\Trans::_('hero_title') }}</h1>
        <p class="hero-subtitle">
            {{ \Alxarafe\Lib\Trans::_('hero_subtitle') }}<br>
            <small class="text-muted">{{ \Alxarafe\Lib\Trans::_('no_cookies') }}</small>
        </p>
        <div class="mt-5">
            <a href="/acerca-de-nosotros" class="btn-alx">{{ \Alxarafe\Lib\Trans::_('learn_more') }}</a>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4 justify-content-center">
        <!-- Simplicidad -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-feather"></i></div>
                <h3 class="feature-title">{{ \Alxarafe\Lib\Trans::_('simplicity') }}</h3>
                <p class="feature-description">
                    {{ \Alxarafe\Lib\Trans::_('simplicity_description') }}
                </p>
                <a href="/acerca-de-nosotros" class="btn-outline-alx">{{ \Alxarafe\Lib\Trans::_('philosophy') }} <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>

        <!-- Markdown -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-code"></i></div>
                <h3 class="feature-title">{{ \Alxarafe\Lib\Trans::_('markdown_native') }}</h3>
                <p class="feature-description">
                    {{ \Alxarafe\Lib\Trans::_('markdown_description') }}
                </p>
                <a href="/blog" class="btn-outline-alx">{{ \Alxarafe\Lib\Trans::_('view_examples') }} <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>

        <!-- Rendimiento -->
        <div class="col-md-4">
            <div class="feature-card">
                <div class="icon-wrapper"><i class="fas fa-bolt"></i></div>
                <h3 class="feature-title">{{ \Alxarafe\Lib\Trans::_('performance') }}</h3>
                <p class="feature-description">
                    {{ \Alxarafe\Lib\Trans::_('performance_description') }}
                </p>
                <a href="/blog" class="btn-outline-alx">{{ \Alxarafe\Lib\Trans::_('laboratory') }} <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>

    @if(!$posts->isEmpty())
        <div class="mt-5 pt-5 border-top">
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <h2 class="h1 fw-800 text-secondary mb-0">{{ \Alxarafe\Lib\Trans::_('latest_posts') }}</h2>
                    <p class="text-muted">{{ \Alxarafe\Lib\Trans::_('news_from_lab') }}</p>
                </div>
                <a href="/blog" class="btn btn-link text-primary fw-bold text-decoration-none p-0">
                    {{ \Alxarafe\Lib\Trans::_('view_all') }} <i class="fas fa-arrow-right ms-1"></i>
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
                                    {{ \Alxarafe\Lib\Trans::_('read_on') }} <i class="fas fa-chevron-right ms-1"></i>
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
