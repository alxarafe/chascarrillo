@extends('layout.public')

@section('content')
<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <h1 class="display-4"><i class="fas fa-vial"></i> Alxarafe Test Page</h1>
            <p class="lead">Si estás viendo esto, el motor de plantillas Blade y el controlador genérico están funcionando correctamente.</p>
            <hr>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Información del Sistema</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Versión PHP
                            <span class="badge bg-primary rounded-pill">{{ $php_version }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Software del Servidor
                            <span class="text-muted small">{{ $server_info['SERVER_SOFTWARE'] ?? 'Unknown' }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Prueba de Componentes</h5>
                </div>
                <div class="card-body">
                    <p>Aquí podríamos mostrar componentes dinámicos generados desde el controlador en el futuro.</p>
                    <div class="alert alert-info py-2">
                        <i class="fas fa-info-circle"></i> Alxarafe está listo para el desarrollo.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
