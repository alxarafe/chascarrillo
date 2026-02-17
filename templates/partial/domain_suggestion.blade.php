@php
    $suggestion = \Modules\Chascarrillo\Service\DomainService::getSuggestion();
@endphp

@if($suggestion)
<div class="alert alert-info border-0 rounded-0 mb-0 py-2" style="background: var(--alx-primary); color: white;">
    <div class="container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-globe"></i>
            <span class="small fw-600">{{ $suggestion['message'] }}</span>
        </div>
        <div class="d-flex gap-3">
            <a href="{{ $suggestion['url'] }}" class="btn btn-sm btn-light py-0 px-3 fw-bold" style="font-size: 0.75rem;">
                Ir a {{ $suggestion['domain'] }}
            </a>
            <button type="button" class="btn-close btn-close-white" style="font-size: 0.6rem;" onclick="document.cookie='skip_domain_suggestion=1; path=/; max-age=86400'; this.closest('.alert').remove();"></button>
        </div>
    </div>
</div>
@endif
