<!DOCTYPE html>
@php
    $activeTheme = (defined('THEME_SKIN') ? constant('THEME_SKIN') : null)
        ?? $_SESSION['alx_theme_test'] 
        ?? $_COOKIE['alx_theme_test']
        ?? \Alxarafe\Infrastructure\Persistence\Config::getConfig()->main->theme
        ?? 'chascarrillo';
@endphp
<html lang="{!! $me->config->main->language ?? 'es' !!}" data-theme="{{ $activeTheme }}">
<head>
    {{-- Chascarrillo-specific: Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Framework head: Bootstrap, Font Awesome, DebugBar, sidebar CSS --}}
    @include('partial.head')



    {{-- SEO: Hreflang Tags --}}
    @if(class_exists(\Modules\Chascarrillo\Service\DomainService::class))
        @foreach(\Modules\Chascarrillo\Service\DomainService::getHreflangs() as $lang => $url)
            <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ \Modules\Chascarrillo\Service\DomainService::getTargetUrl('en') }}" />
    @endif
</head>
<body class="{{ $activeTheme }}-theme theme-{{ $activeTheme }}">
    @include('partial.domain_suggestion')
    @php
        $_body = 'body_' . ($empty ?? false ? 'empty' : 'standard');
    @endphp
    @include('partial.' . $_body)
    @include('partial.footer')
</body>
</html>

