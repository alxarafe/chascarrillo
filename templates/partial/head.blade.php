<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="title" content="{!! $me->title ?? 'Alxarafe' !!}">
<meta name="author" content="Rafael San José">
<meta name="description" content="Microframework for development of PHP database applications">
<title>{!! $me->title ?? 'Alxarafe' !!}</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"
      integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css">
<link href="/alxarafe/assets/css/alxarafe-content.css?v=0.8.1" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">

<!-- Dynamic Theme CSS -->
@php
    $activeTheme = $activeTheme ?? (defined('THEME_SKIN') ? constant('THEME_SKIN') : null)
        ?? $_SESSION['alx_theme_test'] 
        ?? $_COOKIE['alx_theme_test']
        ?? \Alxarafe\Infrastructure\Persistence\Config::getConfig()->main->theme
        ?? 'chascarrillo';
@endphp

@if(file_exists(constant('BASE_PATH') . "/themes/{$activeTheme}/css/default.css"))
    <link href="/themes/{{ $activeTheme }}/css/default.css?v={{ time() }}" rel="stylesheet">
@elseif(file_exists(constant('BASE_PATH') . "/themes/{$activeTheme}/css/alxarafe.css"))
    <link href="/themes/{{ $activeTheme }}/css/alxarafe.css?v={{ time() }}" rel="stylesheet">
@endif

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

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

    /* Top navigation bar (project_menu) */
    .alx-navbar {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.75rem;
        border-bottom: 1px solid #e9ecef;
        background: #fff;
        flex-wrap: nowrap;
        z-index: 1050;
        position: relative;
    }
    .alx-navbar-brand {
        color: #333;
        font-size: 0.95rem;
        white-space: nowrap;
    }
    .alx-navbar-brand:hover { color: #000; }
    .alx-navbar-nav {
        display: flex;
        align-items: center;
    }
    .alx-nav-link {
        color: #555;
        font-size: 0.85rem;
        white-space: nowrap;
        transition: color 0.2s, background 0.2s;
    }
    .alx-nav-link:hover {
        color: #000;
        background: rgba(0,0,0,0.05);
    }
    .alx-navbar-tools a {
        font-size: 0.85rem;
        transition: color 0.2s;
    }
    .alx-navbar-tools a:hover { color: #000 !important; }

    /* Keep tables generated from Markdown inside the article viewport. */
    .post-content table {
        display: block;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Administrative tables retain every column on small screens. */
    .admin-table-scroll {
        max-width: 100%;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        -webkit-overflow-scrolling: touch;
        scrollbar-color: currentColor transparent;
        scrollbar-width: thin;
    }
    .admin-table-scroll > .table {
        min-width: 48rem;
    }
    .admin-table-scroll:focus-visible {
        outline: 3px solid var(--bs-primary, #0d6efd);
        outline-offset: 3px;
    }
    .admin-table-scroll .admin-table-actions {
        position: sticky;
        right: 0;
        z-index: 2;
        background: var(--bs-body-bg, #fff);
        background-clip: padding-box;
        box-shadow: -0.75rem 0 0.75rem -0.75rem rgba(0, 0, 0, 0.45);
    }
    .admin-table-scroll th.admin-table-actions {
        z-index: 3;
    }
    .admin-table-scroll .admin-table-actions :is(a, button):focus-visible {
        outline: 3px solid var(--bs-primary, #0d6efd);
        outline-offset: 2px;
    }
    .admin-table-scroll-hint {
        display: none;
    }

    @media (max-width: 768px) {
        .admin-table-scroll-hint {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin: 0;
            padding: 0.5rem 1rem;
            color: var(--bs-secondary-color, #6c757d);
            font-size: 0.875rem;
        }
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
