@extends('partial.layout.main')

@section('content')
<div class="hero-section">
    <div class="container text-center">
        <h1 class="hero-title">{{ \Alxarafe\Infrastructure\Lib\Trans::_('laboratory_title') }}</h1>
        <p class="hero-subtitle">{{ \Alxarafe\Infrastructure\Lib\Trans::_('laboratory_subtitle') }}</p>
    </div>
</div>

<div class="container py-5">
    @if(isset($posts) && count($posts) > 0)
        <div class="d-grid gap-5">
            @foreach($posts as $post)
            <article class="blog-post pb-5 border-bottom">
                <header class="mb-4">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <span class="text-muted small text-uppercase">
                            {{ \Carbon\Carbon::parse($post->published_at)->format('d F, Y') }}
                        </span>
                        <div class="d-flex gap-2">
                            @foreach($post->tags as $tag)
                                <a href="/blog?tag={{ $tag->slug }}" class="badge rounded-pill bg-primary bg-opacity-10 text-primary text-decoration-none small">
                                    #{{ $tag->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <h2 class="h2 fw-bold mb-3">
                        <a href="/blog/{{ $post->slug }}" class="text-decoration-none text-dark">
                            {{ $post->title }}
                        </a>
                    </h2>
                </header>

                @if(!empty($post->featured_image))
                <div class="mb-4">
                    <a href="/blog/{{ $post->slug }}">
                        <img src="{{ $post->featured_image }}" class="img-fluid rounded-4 shadow-sm" alt="{{ $post->title }}" style="max-height: 400px; width: 100%; object-fit: cover;">
                    </a>
                </div>
                @endif

                <div class="post-excerpt mb-4 text-secondary">
                    <p>
                        {{ $post->meta_description ?? $post->getExcerpt(250) }}
                    </p>
                </div>

                <a href="/blog/{{ $post->slug }}" class="btn btn-outline-alx">
                    {{ \Alxarafe\Infrastructure\Lib\Trans::_('read_on') }} <i class="fas fa-arrow-right small"></i>
                </a>
            </article>
            @endforeach
        </div>
    @else
        <div class="py-5 text-center">
            <i class="fas fa-flask fa-3x text-light mb-4"></i>
            <p class="text-muted fs-5">{{ \Alxarafe\Infrastructure\Lib\Trans::_('no_posts_yet') }}</p>
            <a href="/" class="btn btn-primary rounded-pill mt-3">Volver al inicio</a>
        </div>
    @endif
</div>
@endsection
