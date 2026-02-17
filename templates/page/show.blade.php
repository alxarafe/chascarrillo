@extends('partial.layout.main')

@section('content')
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

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8" style="max-width: 800px;">
            <article class="page-content">
                @if(!empty($page->featured_image))
                    <div class="mb-5">
                        <img src="{{ $page->featured_image }}" class="img-fluid rounded shadow-sm w-100" alt="{{ $page->title }}" style="max-height: 400px; object-fit: cover;">
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
