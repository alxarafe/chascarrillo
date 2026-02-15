@extends('partial.layout.main')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <article class="page-content">
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

                <div class="content-body">
                    {!! $page->getRenderedContent() !!}
                </div>
            </article>
        </div>
    </div>
</div>
@endsection
