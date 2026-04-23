@props([
    'title' => null,
    'image' => null,
    'footer' => null,
    'class' => 'card'
])

<div {{ $attributes->merge(['class' => $class . ' cyber-card bg-dark text-white border-0 position-relative mb-4']) }}>
    <!-- Cyberpunk Decoration -->
    <div class="cyber-corner cyber-corner-tl"></div>
    <div class="cyber-corner cyber-corner-br"></div>
    
    @if($image)
        <img src="{{ $image }}" class="card-img-top filter-cyber" alt="{{ $title ?? 'Card image' }}">
    @endif
    
    <div class="card-body p-4">
        @if($title)
            <h4 class="card-title text-uppercase border-bottom border-secondary pb-2 mb-3">
                <span class="text-info">></span> {{ $title }}
            </h4>
        @endif
        {{ $slot }}
    </div>
    
    @if($footer)
        <div class="card-footer bg-transparent border-top border-secondary">
            <small class="text-muted font-monospace">{{ $footer }}</small>
        </div>
    @endif
</div>
