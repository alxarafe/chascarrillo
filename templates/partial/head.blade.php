<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="title" content="{!! $me->title ?? 'Alxarafe' !!}">
<meta name="author" content="Rafael San José">
<meta name="description" content="Microframework for development of PHP database applications">
<title>{!! $me->title ?? 'Alxarafe' !!}</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"
      integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<!-- Dynamic Theme CSS -->
@php
    $activeTheme = (defined('THEME_SKIN') ? constant('THEME_SKIN') : null)
        ?? $_SESSION['alx_theme_test'] 
        ?? $_COOKIE['alx_theme_test']
        ?? \Alxarafe\Base\Config::getConfig()->main->theme
        ?? 'chascarrillo';
@endphp

@if(file_exists(constant('BASE_PATH') . "/themes/{$activeTheme}/css/default.css"))
    <link href="/themes/{{ $activeTheme }}/css/default.css?v={{ time() }}" rel="stylesheet">
@elseif(file_exists(constant('BASE_PATH') . "/themes/{$activeTheme}/css/alxarafe.css"))
    <link href="/themes/{{ $activeTheme }}/css/alxarafe.css?v={{ time() }}" rel="stylesheet">
@endif

{!! $me->getRenderHeader() !!}

<style>
    /* Default Sidebar layout override */
    .sidebar {
        height: 100vh;
        width: 250px;
        position: fixed;
        top: 0;
        left: 0;
        padding-top: 20px;
        z-index: 1000;
    }

    .no-sidebar .sidebar {
        display: none;
    }
    
    .id_container {
        display: flex;
        flex-direction: row;
        min-height: 100vh;
    }
    
    #id-right {
        margin-left: 0;
        padding: 20px;
        flex: 1;
        min-width: 0;
        transition: margin-left 0.3s;
    }

    .has-sidebar #id-right {
        margin-left: 250px; /* Sidebar width */
        width: calc(100% - 250px);
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 0;
            overflow: hidden;
        }
        .has-sidebar #id-right {
            margin-left: 0;
            width: 100%;
        }
    }

</style>

@stack('css')
