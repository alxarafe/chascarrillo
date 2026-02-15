@extends('partial.layout.main')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <article class="post-content">
                <header class="mb-5">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill small fw-bold">
                            {{ $post->published_at ? $post->published_at->format('d M, Y') : 'Borrador' }}
                        </span>
                        @if($me->isAdmin())
                            <a href="index.php?module=Chascarrillo&controller=Blog&action=edit&id={{ $post->id }}" class="btn btn-sm btn-link text-muted text-decoration-none">
                                <i class="fas fa-edit"></i>
                            </a>
                        @endif
                    </div>
                    <h1 class="display-4 fw-800 text-secondary mb-3">{{ $post->title }}</h1>
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
                        <a href="?module=Chascarrillo&controller=Blog&action=index" class="btn btn-outline-primary rounded-pill">
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

<style>
    .content-body h2 { font-weight: 700; color: var(--alx-secondary); margin-top: 2.5rem; margin-bottom: 1.25rem; }
    .content-body p { color: #4b5563; font-size: 1.1rem; line-height: 1.8; margin-bottom: 1.5rem; }
    .content-body ul, .content-body ol { margin-bottom: 1.5rem; padding-left: 1.5rem; color: #4b5563; }
    .content-body li { margin-bottom: 0.5rem; }
    .content-body blockquote { border-left: 4px solid var(--alx-primary); padding-left: 1.5rem; font-style: italic; color: #6b7280; margin: 2rem 0; }
</style>
@endsection
