<!DOCTYPE html>
<html lang="{!! $me->config->main->language ?? 'es' !!}" data-theme="chascarrillo">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{!! $me->title !!} | Chascarrillo</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Theme CSS -->
    <link href="/themes/chascarrillo/css/alxarafe.css?v={{ time() }}" rel="stylesheet">
    <link href="/css/alxarafe-content.css?v={{ time() }}" rel="stylesheet">

    @if(class_exists(\Modules\Chascarrillo\Service\DomainService::class))
        @foreach(\Modules\Chascarrillo\Service\DomainService::getHreflangs() as $lang => $url)
            <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ \Modules\Chascarrillo\Service\DomainService::getTargetDomain('en') }}" />
    @endif

    {!! $me->getRenderHeader() !!}
</head>
<body class="chascarrillo-theme theme-chascarrillo">
    @php
        $_body = 'body_' . ($empty ?? false ? 'empty' : 'standard');
    @endphp
    @include('partial.domain_suggestion')
    @include('partial.' . $_body)
    @include('partial.footer')
</body>
</html>
