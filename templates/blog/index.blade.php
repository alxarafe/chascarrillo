@extends('layout.public')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-5 border-bottom pb-4">
                <h1 class="display-5 fw-bold mb-3">{{ $active_module_title ?? 'Explora el Desarrollo de Alxarafe' }}</h1>
                <p class="lead text-muted">Ingeniería, IA y cimientos sólidos.</p>
            </div>

            @if(isset($posts) && count($posts) > 0)
                <div class="d-grid gap-5">
                    @foreach($posts as $post)
                    <article class="blog-post">
                        <header class="mb-3">
                            <h2 class="h3 fw-bold mb-1">
                                <a href="{{ $me::url('show', ['slug' => $post->slug]) }}" class="text-decoration-none text-dark">
                                    {{ $post->title }}
                                </a>
                            </h2>
                            <small class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">
                                {{ \Carbon\Carbon::parse($post->published_at)->format('d F, Y') }}
                            </small>
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

                        <a href="{{ $me::url('show', ['slug' => $post->slug]) }}" class="btn btn-link p-0 text-decoration-none text-dark fw-bold" style="font-size: 0.9rem;">
                            Leer entrada &raquo;
                        </a>
                    </article>
                    @endforeach
                </div>
            @else
                <div class="py-5 text-center">
                    <p class="text-muted">No hay entradas publicadas todavía.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
