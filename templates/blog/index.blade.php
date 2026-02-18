@extends('partial.layout.main')

@section('content')
<div class="hero-section">
    <div class="container text-center">
        <h1 class="hero-title">{{ $title ?? \Alxarafe\Lib\Trans::_('laboratory_title') }}</h1>
        <p class="hero-subtitle">{{ \Alxarafe\Lib\Trans::_('laboratory_subtitle') }}</p>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            @if(isset($posts) && count($posts) > 0)
                <div class="d-grid gap-5">
                    @foreach($posts as $post)
                    <article class="blog-post">
                        <header class="mb-3">
                            <h2 class="h3 fw-bold mb-1">
                                <a href="/blog/{{ $post->slug }}" class="text-decoration-none text-dark">
                                    {{ $post->title }}
                                </a>
                            </h2>
                            <small class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">
                                {{ \Carbon\Carbon::parse($post->published_at)->format('d F, Y') }}
                            </small>

                            <div class="mt-2">
                                @foreach($post->tags->where('type', 'category') as $category)
                                    <a href="/blog?category={{ $category->slug }}" class="badge rounded-pill bg-primary bg-opacity-10 text-primary text-decoration-none me-1" style="font-size: 0.7rem;">
                                        <i class="fas fa-folder me-1"></i>{{ $category->name }}
                                    </a>
                                @endforeach
                                @foreach($post->tags->where('type', 'tag') as $tag)
                                    <a href="/blog?tag={{ $tag->slug }}" class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary text-decoration-none me-1" style="font-size: 0.7rem;">
                                        <i class="fas fa-tag me-1"></i>{{ $tag->name }}
                                    </a>
                                @endforeach
                            </div>
                        </header>
                        
                        @if(!empty($post->featured_image))
                        <div class="mb-3">
                            <a href="{{ $me::url('show', ['slug' => $post->slug]) }}">
                                <img src="{{ $post->featured_image }}" class="img-fluid rounded" alt="{{ $post->title }}" style="max-height: 250px; width: 100%; object-fit: cover; opacity: 0.9; transition: opacity 0.3s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.9">
                            </a>
                        </div>
                        @endif
                        
                        <div class="post-excerpt mb-3 text-secondary">
                            <p class="mb-0">
                                {{ \Illuminate\Support\Str::limit(strip_tags($post->meta_description ?? $post->content), 250) }}
                            </p>
                        </div>

                        <a href="/blog/{{ $post->slug }}" class="btn btn-link p-0 text-decoration-none text-dark fw-bold" style="font-size: 0.9rem;">
                            {{ \Alxarafe\Lib\Trans::_('follow_reading') }} &raquo;
                        </a>
                    </article>
                    @endforeach
                </div>
            @else
                <div class="py-5 text-center">
                    <p class="text-muted">{{ \Alxarafe\Lib\Trans::_('no_posts_yet') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
