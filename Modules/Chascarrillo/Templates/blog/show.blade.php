@extends('partial.layout.main')

@php
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $postUrl = $scheme . '://' . $host . '/blog/' . $post->slug;
    $postTitle = $post->title;
    $encodedUrl = urlencode($postUrl);
    $encodedTitle = urlencode($postTitle);
@endphp

@section('content')
<div class="container py-5">
    <article class="post-content">
        <header class="mb-5">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill small fw-bold">
                    {{ $post->published_at ? $post->published_at->format('d M, Y') : 'Borrador' }}
                </span>
                @if(\Alxarafe\Infrastructure\Auth\Auth::isLogged() && \Alxarafe\Infrastructure\Auth\Auth::$user->is_admin)
                    <a href="/?module=Chascarrillo&controller=Post&action=edit&id={{ $post->id }}" class="btn btn-sm btn-warning rounded-pill px-3 shadow-sm">
                        <i class="fas fa-edit me-1"></i> Editar Chascarrillo
                    </a>
                @endif
            </div>
            @if(!str_contains($post->content, '# ' . $post->title))
                <h1 class="display-4 fw-800 text-secondary mb-3">{{ $post->title }}</h1>
            @endif
        </header>

        @if(!empty($post->featured_image))
            <div class="mb-5 rounded-4 overflow-hidden shadow-sm">
                <img src="{{ $post->featured_image }}" class="img-fluid w-100" alt="{{ $post->title }}">
            </div>
        @endif

        <div class="content-body">
            {!! $post->getRenderedContent() !!}
        </div>

        <footer class="mt-5 pt-5 border-top">
            {{-- Tags y categorías del post --}}
            @if($post->tags->count() > 0)
            <div class="mb-4 d-flex flex-wrap gap-2">
                @foreach($post->tags->where('type', 'category') as $cat)
                    <a href="/blog?category={{ $cat->slug }}" class="badge rounded-pill bg-primary bg-opacity-10 text-primary text-decoration-none">
                        <i class="fas fa-folder-open me-1"></i>{{ $cat->name }}
                    </a>
                @endforeach
                @foreach($post->tags->where('type', 'tag') as $tag)
                    <a href="/blog?tag={{ $tag->slug }}" class="badge rounded-pill bg-success bg-opacity-10 text-success text-decoration-none">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
            @endif

            <div class="d-flex justify-content-between align-items-center">
                <a href="/blog" class="btn btn-outline-alx">
                    <i class="fas fa-arrow-left me-2"></i> Volver al blog
                </a>
                <div class="social-share d-flex align-items-center gap-2">
                    <span class="text-muted small me-2">Compartir:</span>
                    <a href="https://twitter.com/intent/tweet?url={{ $encodedUrl }}&text={{ $encodedTitle }}" target="_blank" rel="noopener noreferrer" class="text-secondary hover-primary" title="Compartir en X/Twitter">
                        <i class="fab fa-x-twitter fa-lg"></i>
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encodedUrl }}" target="_blank" rel="noopener noreferrer" class="text-secondary hover-primary" title="Compartir en LinkedIn">
                        <i class="fab fa-linkedin fa-lg"></i>
                    </a>
                    <a href="https://api.whatsapp.com/send?text={{ $encodedTitle }}%20{{ $encodedUrl }}" target="_blank" rel="noopener noreferrer" class="text-secondary hover-primary" title="Compartir por WhatsApp">
                        <i class="fab fa-whatsapp fa-lg"></i>
                    </a>
                    <a href="mailto:?subject={{ $encodedTitle }}&body={{ $encodedUrl }}" class="text-secondary hover-primary" title="Compartir por email">
                        <i class="fas fa-envelope fa-lg"></i>
                    </a>
                </div>
            </div>
        </footer>
    </article>
</div>
@endsection
