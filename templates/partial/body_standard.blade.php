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

