<!DOCTYPE html>
<html lang="{!! $me->config->main->language ?? 'es' !!}" data-theme="chascarrillo">
<head>
    {{-- Chascarrillo-specific: Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Override theme_css section to load Chascarrillo CSS instead of default --}}
    @section('theme_css')
        <link href="/themes/chascarrillo/css/alxarafe.css?v={{ time() }}" rel="stylesheet">
    @endsection

    {{-- Framework head: Bootstrap, Font Awesome, DebugBar, sidebar CSS --}}
    @include('partial.head')

    {{-- Chascarrillo-specific: Content styles --}}
    <link href="/css/alxarafe-content.css?v={{ time() }}" rel="stylesheet">

    {{-- SEO: Hreflang Tags --}}
    @if(class_exists(\Modules\Chascarrillo\Service\DomainService::class))
        @foreach(\Modules\Chascarrillo\Service\DomainService::getHreflangs() as $lang => $url)
            <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ \Modules\Chascarrillo\Service\DomainService::getTargetUrl('en') }}" />
    @endif
</head>
<body class="chascarrillo-theme theme-chascarrillo">
    @include('partial.domain_suggestion')
    @php
        $_body = 'body_' . ($empty ?? false ? 'empty' : 'standard');
    @endphp
    @include('partial.' . $_body)
    @include('partial.footer')
</body>
</html>

