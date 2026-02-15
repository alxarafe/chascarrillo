<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Alxarafe' }}</title>
    @if(!empty($meta_description))
    <meta name="description" content="{{ $meta_description }}">
    @endif
    @if(!empty($meta_keywords))
    <meta name="keywords" content="{{ $meta_keywords }}">
    @endif

    <!-- Open Graph (OG) -->
    <meta property="og:title" content="{{ $title ?? 'Alxarafe' }}">
    <meta property="og:description" content="{{ $meta_description ?? 'Microframework PHP' }}">
    <meta property="og:type" content="website">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bs-body-font-family: 'Inter', sans-serif;
            --bs-body-color: #333;
            --bs-primary: #000;
            --bs-link-color: #000;
            --bs-link-hover-color: #555;
        }
        
        body {
            background-color: #fff;
            padding-top: 80px;
        }

        .navbar {
            background-color: #fff;
            border-bottom: 1px solid #eaeaea;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
        
        .navbar-brand {
            color: #000 !important;
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .nav-link {
            color: #555 !important;
            font-weight: 500;
            font-size: 0.95rem;
            margin-left: 1rem;
            transition: color 0.2s;
        }
        
        .nav-link:hover {
            color: #000 !important;
        }

        .nav-link.active {
            color: #000 !important;
            font-weight: 600;
        }

        footer {
            margin-top: 80px;
            padding: 40px 0;
            background: #fff;
            border-top: 1px solid #eaeaea;
            color: #666;
            font-size: 0.9rem;
        }

        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #111;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/">
                Alxarafe
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Funcionalidades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Documentación</a>
                    </li>
                    <li class="nav-item">
                         <a class="nav-link" href="/admin">Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    <footer class="text-center">
        <div class="container">
            <p class="mb-1">Copyright © {{ date('Y') }} Alxarafe Framework</p>
            <small class="text-muted">Desarrollo Rápido y Limpio</small>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
