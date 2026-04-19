@extends('partial.layout.main')

@section('content')
<div class="hero-section">
    <div class="container text-center">
        @if(isset($tag_filter) && $tag_filter)
            <div class="mb-3">
                <span class="badge rounded-pill bg-success px-3 py-2 text-uppercase shadow-sm d-inline-flex align-items-center">
                    <i class="fas fa-hashtag me-2"></i>{{ $tag_filter }}
                    <a href="/blog" class="text-white ms-2 text-decoration-none" title="Eliminar filtro"><i class="fas fa-circle-xmark"></i></a>
                </span>
            </div>
        @elseif(isset($cat_filter) && $cat_filter)
            <div class="mb-3">
                <span class="badge rounded-pill bg-primary px-3 py-2 text-uppercase shadow-sm d-inline-flex align-items-center">
                    <i class="fas fa-folder-open me-2"></i>{{ $cat_filter }}
                    <a href="/blog" class="text-white ms-2 text-decoration-none" title="Eliminar filtro"><i class="fas fa-circle-xmark"></i></a>
                </span>
            </div>
        @endif
        <h1 class="hero-title">{{ $hero_title ?? \Alxarafe\Infrastructure\Lib\Trans::_('laboratory_title') }}</h1>
        <p class="hero-subtitle">{{ $hero_subtitle ?? \Alxarafe\Infrastructure\Lib\Trans::_('laboratory_subtitle') }}</p>
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
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach($post->tags->where('type', 'category') as $cat)
                                <a href="/blog?category={{ $cat->slug }}" class="badge rounded-pill bg-primary bg-opacity-10 text-primary text-decoration-none small">
                                    <i class="fas fa-folder-open me-1"></i>{{ $cat->name }}
                                </a>
                            @endforeach
                            @foreach($post->tags->where('type', 'tag') as $tag)
                                <a href="/blog?tag={{ $tag->slug }}" class="badge rounded-pill bg-success bg-opacity-10 text-success text-decoration-none small">
                                    #{{ $tag->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <h2 class="h2 fw-bold mb-3">
                        <a href="/blog/{{ $post->slug }}" class="text-decoration-none text-reset">
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

                <div class="post-excerpt mb-4 opacity-75">
                    <p>
                        {{ $post->meta_description ?? $post->getExcerpt(250) }}
                    </p>
                </div>

                <a href="/blog/{{ $post->slug }}" class="btn btn-outline-primary rounded-pill px-4 fw-semibold">
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
