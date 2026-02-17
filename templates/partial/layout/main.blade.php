<!DOCTYPE html>
<html lang="{!! $me->config->main->language ?? 'es' !!}" data-theme="{!! $me->config->main->theme ?? 'default' !!}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{!! $me->title !!}</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Alxarafe Content Styles -->
    <link href="/css/alxarafe-content.css?v={{ time() }}" rel="stylesheet">

    {!! $me->getRenderHeader() !!}
</head>
<body class="theme-{!! $me->config->main->theme ?? 'default' !!}">
    @php
        $_body = 'body_' . ($empty ?? false ? 'empty' : 'standard');
    @endphp
    @include('partial.' . $_body)
    @include('partial.footer')
</body>
</html>
