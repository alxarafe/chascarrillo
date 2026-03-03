@php
    $hasSidebar = \Alxarafe\Lib\Auth::isLogged() && !empty($main_menu);
@endphp
<div id="id_container" class="id_container {{ $hasSidebar ? 'has-sidebar' : 'no-sidebar' }}">
    
    @if($hasSidebar)
        @include('partial.main_menu')
    @endif

        <div id="id-right">
            @include('partial.project_menu')
            
            <div class="container-fluid mt-3 px-4">
                 <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0 fw-bold">{!! $me->title !!}</h2>
                    <div class="d-flex gap-2">
                        @yield('header_actions')
                    </div>
                 </div>
                 @include('partial.alerts')
            </div>

            @yield('content')
        </div>
</div>

