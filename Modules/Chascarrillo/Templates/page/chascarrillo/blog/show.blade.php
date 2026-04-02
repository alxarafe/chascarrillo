@extends('partial.layout.main')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-{{ isset($hasSidebar) && $hasSidebar ? '8' : '10' }}">
            <article class="post-content">
                <header class="mb-5">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill small fw-bold">
                            {{ $post->published_at ? $post->published_at->format('d M, Y') : 'Borrador' }}
                        </span>
                        @if(\Alxarafe\Infrastructure\Auth\Auth::isLogged() && \Alxarafe\Infrastructure\Auth\Auth::$user->is_admin)
                            <a href="/index.php?module=Chascarrillo&controller=Post&action=edit&id={{ $post->id }}" class="btn btn-sm btn-warning rounded-pill px-3 shadow-sm">
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
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="/index.php?module=Chascarrillo&controller=Blog&action=index" class="btn btn-outline-primary rounded-pill">
                            <i class="fas fa-arrow-left me-2"></i> Volver al blog
                        </a>
                        <div class="social-share">
                            <span class="text-muted small me-2">Compartir:</span>
                            <a href="#" class="text-secondary hover-primary me-2"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="text-secondary hover-primary me-2"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                </footer>
            </article>
        </div>
    </div>
</div>

@endsection

