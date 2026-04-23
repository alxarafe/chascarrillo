@props([
    'title' => null,
    'image' => null,
    'footer' => null,
])

@php
    $title = $title ? "PANEL: " . strtoupper($title) : null;
@endphp

<div {{ $attributes->merge(['class' => 'card hc-card mb-5 border-5']) }}>
    @if($image)
        <!-- Adding explicit ALT text if missing -->
        <img src="{{ $image }}" class="card-img-top border-bottom-5" alt="{{ $title ?? 'Descriptive Image' }}">
    @endif
    <div class="card-body p-4">
        @if($title)
            <h2 class="card-title text-uppercase border-bottom pb-2 mb-4" style="color: yellow !important; border-color: white !important;">
                <i class="fas fa-arrow-right" aria-hidden="true"></i> {{ $title }}
            </h2>
        @endif
        
        <div class="hc-content" style="font-size: 1.2em;">
            {{ $slot }}
        </div>
    </div>
    
    @if($footer)
        <div class="card-footer bg-black text-white p-3 border-top-2">
            <strong>INFO: </strong> {{ $footer }}
        </div>
    @endif
</div>
