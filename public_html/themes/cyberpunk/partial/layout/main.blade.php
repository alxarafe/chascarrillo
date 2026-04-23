<!DOCTYPE html>
<html lang="{!! $me->config->main->language ?? 'en' !!}">
<head>
    <title>CYBER::{!! $me->title !!}</title>
    @include('partial.head')
</head>
<body class="cyber-shell">

@include('partial.user_menu')

<div class="cyber-grid-overlay"></div>

@php
    $hasSidebar = !empty($main_menu);
@endphp

<style>
    .cyber-main-content {
        margin-right: 0;
        margin-bottom: 70px; /* Space for Bottom Bar on Mobile */
    }
    @media (min-width: 768px) {
        .has-sidebar #id-right {
            margin-left: 250px !important;
        }
        .cyber-main-content {
            margin-right: 110px; /* Space for Right Bar on Desktop */
            margin-bottom: 0;
        }
    }
</style>

<div class="container-fluid">
    <div class="row">
        @if($hasSidebar)
        <!-- Sidebar Column (Hidden on Mobile unless toggled) -->
        <div class="col-md-3 col-lg-2 d-none d-md-block p-0" id="cyber-sidebar-col">
             @include('partial.main_menu')
        </div>
        @endif

        <!-- Main Content -->
        <main class="{{ $hasSidebar ? 'col-md-9 ms-sm-auto col-lg-10' : 'col-12' }} px-md-4 py-4">
            <div class="cyber-main-content">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
                    <h1 class="cyber-page-title mb-0 d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-info me-3 d-md-none" onclick="document.getElementById('cyber-sidebar-col').classList.toggle('d-none');">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        @if(!empty($me->backUrl))
                            <a href="{!! $me->backUrl !!}" class="text-decoration-none text-danger me-2">&lt;</a>
                        @endif
                        > {{ strtoupper($me->title ?? 'Alxarafe') }}_
                    </h1>
                    <div class="cyber-actions">
                        @yield('header_actions')
                    </div>
                </div>
                
                @php
                    $_body = 'body_' . ($empty ?? false ? 'empty' : 'standard');
                @endphp
                
                <!-- We override the body structure slightly here by standardising content wrapper -->
                <div class="cyber-content-wrapper">
                     @include('partial.alerts')
                     @yield('content')
                </div>
                
                @include('partial.footer')
            </div>
        </main>
    </div>
</div>

</body>
</html>
