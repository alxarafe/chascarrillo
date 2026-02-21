@extends('partial.layout.main')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <article class="page-content">
                @if($page->slug !== 'index' && !str_contains($page->content, '# ' . $page->title))
                <header class="mb-5 text-center">
                    <h1 class="display-4 fw-800 text-secondary mb-3">{{ $page->title }}</h1>
                    @if($me->isAdmin())
                        <div class="mt-2 text-center">
                            <a href="index.php?module=Chascarrillo&controller=Page&action=edit&id={{ $page->id }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="fas fa-edit me-1"></i> Editar página
                            </a>
                        </div>
                    @endif
                </header>
                @endif

                <div class="content-body">
                    {!! $page->getRenderedContent() !!}
                </div>
            </article>
        </div>
    </div>
</div>

<style>
    .content-body h1 { font-size: calc(1.475rem + 2.7vw); font-weight: 800; color: var(--bs-secondary); margin-bottom: 1.5rem; }
    .content-body h2 { font-weight: 700; color: var(--alx-secondary); margin-top: 2.5rem; margin-bottom: 1.25rem; }
</style>
@endsection
