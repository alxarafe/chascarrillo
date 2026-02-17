<div id="id_container" class="id_container {{ \Alxarafe\Lib\Auth::isLogged() ? 'auth-mode' : 'public-mode' }}">
    
    {{-- Solo mostramos el menú lateral si el usuario está identificado --}}
    @if(\Alxarafe\Lib\Auth::isLogged())
        @include('partial.main_menu')
    @endif

    <div id="id-right" class="w-100">
        {{-- El menú superior (Header) es siempre visible y centralizado en el proyecto --}}
        @include('partial.project_menu')
        
        <!-- Alerts/Messages -->
        <div class="container mt-3">
             @include('partial.alerts')
        </div>

        @yield('content')
    </div>
</div>

<style>
    .id_container { display: flex; min-height: 100vh; overflow-x: hidden; }
    
    /* Modo Público: Barra lateral oculta */
    .public-mode .sidebar { display: none !important; }

    /* Modo Auth: Barra lateral a la izquierda */
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
