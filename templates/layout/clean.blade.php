<!DOCTYPE html>
<html lang="{!! $me->config->main->language ?? 'en' !!}">
<head>
    <title>{!! $me->title !!}</title>
    @include('partial.head')
    <style>
        body {
            background: linear-gradient(135deg, #f0f2f5 0%, #e5e7eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .clean-container {
            width: 100%;
            max-width: 1200px;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="clean-container">
        @include('partial.alerts')
        @yield('content')
    </div>
    @include('partial.footer')
</body>
</html>
