@extends('partial.layout.main')

@section('content')
@if($page->slug !== 'index' && !str_contains($page->content, '# ' . $page->title))
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">{{ $page->title }}</h1>
        @if(!empty($page->meta_description))
            <p class="hero-subtitle">{{ $page->meta_description }}</p>
        @endif
        
        @if($me->isAdmin())
            <div class="mt-4">
                <a href="index.php?module=Chascarrillo&controller=Page&action=edit&id={{ $page->id }}" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                    <i class="fas fa-edit me-1"></i> Editar página
                </a>
            </div>
        @endif
    </div>
</div>
@endif

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <article class="page-content">
                @if($page->slug !== 'index' && !empty($page->featured_image))
                    <div class="mb-5 text-center">
                        <img src="{{ $page->featured_image }}" class="img-fluid rounded-4 shadow-sm" alt="{{ $page->title }}" style="max-height: 500px; object-fit: cover; width: 100%;">
                    </div>
                @endif
                
                <div class="post-content fs-5" style="line-height: 1.8; color: inherit;">
                    {!! $page->getRenderedContent() !!}
                </div>
            </article>
        </div>
    </div>
</div>
@endsection
