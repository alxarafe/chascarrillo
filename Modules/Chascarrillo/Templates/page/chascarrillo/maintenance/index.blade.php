@extends('partial.layout.main')

@section('content')
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">Sistema de Actualización</h1>
        <p class="hero-subtitle">Gestiona la versión de tu instalación de Chascarrillo.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 overlay-hidden">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center mb-4">
                        <div class="icon-wrapper me-3 bg-primary-soft text-primary">
                            <i class="fas fa-microchip fa-2x"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0">Información del Sistema</h3>
                            <p class="text-muted mb-0">Versión actual: <span class="badge bg-secondary">{{ $currentVersion }}</span></p>
                        </div>
                    </div>

                    @if($updateInfo)
                        <div class="alert alert-info border-0 rounded-4 p-4 mb-4">
                            <div class="d-flex">
                                <i class="fas fa-rocket fa-2x me-3"></i>
                                <div>
                                    <h4 class="alert-heading fw-bold">¡Nueva versión disponible: {{ $updateInfo['tag_name'] }}!</h4>
                                    <p class="mb-3">{{ $updateInfo['name'] ?? 'Mejoras y correcciones generales.' }}</p>
                                    <hr>
                                    <p class="small mb-0">Recomendamos realizar una copia de seguridad antes de proceder.</p>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <a href="index.php?module=Chascarrillo&controller=Maintenance&action=update" 
                               class="btn btn-primary btn-lg rounded-pill shadow-sm"
                               onclick="return confirm('¿Estás seguro de que deseas actualizar el sistema ahora? Los archivos serán sobrescritos.')">
                                <i class="fas fa-download me-2"></i> Descargar e Instalar Actualización
                            </a>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                            <h4 class="fw-bold">Tu sistema está al día</h4>
                            <p class="text-muted">No se han encontrado versiones más recientes en este momento.</p>
                            
                            <a href="index.php?module=Chascarrillo&controller=Maintenance" class="btn btn-outline-secondary btn-sm mt-3 rounded-pill">
                                <i class="fas fa-sync me-1"></i> Volver a comprobar
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <p class="text-muted small">
                    <i class="fas fa-shield-alt me-1"></i>
                    Tus archivos de configuración (<code>config.json</code>, <code>.env</code>) están protegidos y no serán sobrescritos durante el proceso.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
