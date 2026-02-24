@php
    $hasSidebar = \Alxarafe\Lib\Auth::isLogged() && !empty($main_menu);
@endphp
<div id="id_container" class="id_container {{ $hasSidebar ? 'auth-mode' : 'public-mode' }}">
    
    {{-- Show sidebar only if user is logged in and has menu items --}}
    @if($hasSidebar)
        @include('partial.main_menu')
    @endif

    <div id="id-right" class="w-100">
        {{-- Main navigation header (Project Menu) is always visible --}}
        @include('partial.project_menu')
        
        <!-- Alerts/Messages -->
        <div class="container mt-3">
             @include('partial.alerts')
        </div>

        @yield('content')
    </div>
</div>

<style>
    .id_container { display: flex; min-height: 100vh; }
    
    /* Public Mode: Sidebars hidden */
    .public-mode .sidebar { display: none !important; }

    /* Auth Mode: Sidebar on the left */
    .auth-mode .sidebar { 
        display: block !important;
        width: 250px !important; 
        min-width: 250px !important;
        background: #fff !important; 
        border-right: 1px solid #e5e7eb !important;
        height: 100vh !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 900 !important;
        transition: all 0.3s ease;
    }
    
    .auth-mode #id-right { 
        flex: 1 !important;
        margin-left: 0 !important;
        min-width: 0;
    }

    @media (max-width: 991px) {
        .auth-mode .sidebar { position: fixed !important; left: -250px !important; }
        .auth-mode.toggled .sidebar { left: 0 !important; }
    }
</style>
