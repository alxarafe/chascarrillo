@php
    $hasSidebar = \Alxarafe\Lib\Auth::isLogged() && !empty($main_menu);
@endphp
<div id="id_container" class="id_container {{ $hasSidebar ? 'has-sidebar' : 'no-sidebar' }}">
    
    @if($hasSidebar)
        @include('partial.main_menu')
    @endif

    <div id="id-right" class="w-100">
        @include('partial.project_menu')
        
        <div class="container mt-3">
             @include('partial.alerts')
        </div>

        @yield('content')
    </div>
</div>

<style>
    .id_container { display: flex; min-height: 100vh; }
    
    /* Chascarrillo Sidebar Customization */
    .has-sidebar .sidebar { 
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
    
    .has-sidebar #id-right { 
        flex: 1 !important;
        margin-left: 0 !important; /* Reset margin from head.blade.php since we use flex */
        min-width: 0;
    }

    @media (max-width: 991px) {
        .has-sidebar .sidebar { position: fixed !important; left: -250px !important; }
        .has-sidebar.toggled .sidebar { left: 0 !important; }
    }
</style>

