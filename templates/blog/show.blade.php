@extends('layout.public')

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
                </header>
                
                <div class="post-content fs-5" style="line-height: 1.8; color: #2c2c2c;">
                    {!! $post->content !!}
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
