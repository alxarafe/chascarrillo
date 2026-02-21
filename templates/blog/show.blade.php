@extends('partial.layout.main')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8" style="max-width: 720px;">
            <article class="blog-post">
                <header class="mb-5 text-center">
                    <div class="text-muted text-uppercase mb-2" style="font-size: 0.8rem; letter-spacing: 2px;">
                        {{ \Carbon\Carbon::parse($post->published_at)->format('d F, Y') }}
                    </div>
                    <h1 class="display-4 fw-bold mb-3" style="letter-spacing: -1px;">{{ $post->title }}</h1>
                    @if(!empty($post->featured_image))
                        <div class="mb-4">
                            <img src="{{ $post->featured_image }}" class="img-fluid rounded" alt="{{ $post->title }}" style="max-height: 500px; width: 100%; object-fit: cover;">
                        </div>
                    @endif
                    @if(!empty($post->meta_description))
                        <p class="lead text-secondary mt-3 mx-auto" style="max-width: 80%;">{{ $post->meta_description }}</p>
                    @endif

                    <div class="mt-4 mb-2">
                        <!-- Debug: Admin check -->
                        @if(\Alxarafe\Lib\Auth::isLogged() && \Alxarafe\Lib\Auth::$user->is_admin)
                            <div class="alert alert-info py-1 px-3 d-inline-block small mb-3">Modo Administrador activo</div>
                            <div class="mb-4">
                                <a href="/index.php?module=Chascarrillo&controller=Post&action=edit&id={{ $post->id }}" class="btn btn-warning rounded-pill px-4 shadow-sm">
                                    <i class="fas fa-edit me-2"></i> Editar este Chascarrillo
                                </a>
                            </div>
                        @endif
                        @foreach($post->tags->where('type', 'category') as $category)
                            <a href="/blog?category={{ $category->slug }}" class="badge rounded-pill bg-primary bg-opacity-10 text-primary text-decoration-none me-2 p-2 px-3">
                                <i class="fas fa-folder me-1"></i>{{ $category->name }}
                            </a>
                        @endforeach
                        @foreach($post->tags->where('type', 'tag') as $tag)
                            <a href="/blog?tag={{ $tag->slug }}" class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary text-decoration-none me-2 p-2 px-3">
                                <i class="fas fa-tag me-1"></i>{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </header>
                
                <div class="post-content fs-5" style="line-height: 1.8; color: inherit;">
                    {!! $content !!}
                </div>
            </article>
            
            <div class="mt-5 pt-5 mb-5 border-top">
                <a href="{{ \Modules\Chascarrillo\Controller\BlogController::url('index') }}" class="text-decoration-none text-muted fw-bold">
                    &laquo; Volver al laboratorio
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
