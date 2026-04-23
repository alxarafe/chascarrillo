<div class="sidebar cyber-sidebar">
    <div class="cyber-scanline"></div>
    <div class="cyber-logo text-center py-4">
        <h2 class="glitch" data-text="ALXARAFE">ALXARAFE</h2>
        <small class="text-secondary">> SYS.ONLINE</small>
    </div>

    <!-- Main Menu -->
    @if(!empty($main_menu) && is_array($main_menu))
        <div class="cyber-section mb-4">
            <h6 class="cyber-header px-3 mt-4 text-secondary">
                <i class="fas fa-terminal me-2"></i>NAVIGATION
            </h6>
            <div class="d-flex flex-column">
            @foreach($main_menu as $item)
                <a href="{{ $item['url'] }}" class="cyber-link d-block px-3 py-2" title="{{ $item['label'] }}">
                    <span class="cyber-marker">[ ]</span> 
                    @if(!empty($item['icon']))<i class="{{ $item['icon'] }} me-2"></i>@endif
                    {{ strtoupper($item['label']) }}
                </a>
                @if(!empty($item['children']))
                    <div class="cyber-submenu ps-4">
                        @foreach($item['children'] as $child)
                            <a href="{{ $child['url'] }}" class="cyber-link d-block px-3 py-1 text-muted" title="{{ $child['label'] }}" style="font-size: 0.9em;">
                                <span class="cyber-marker">-</span> 
                                @if(!empty($child['icon']))<i class="{{ $child['icon'] }} me-2"></i>@endif
                                {{ strtoupper($child['label']) }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
            </div>
        </div>
    @endif



    <div class="cyber-footer mt-5 px-3">
        <small class="text-muted d-block code-font">IP: 127.0.0.1</small>
        <small class="text-muted d-block code-font">UPTIME: 99.9%</small>
    </div>
</div>
