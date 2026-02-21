@extends('partial.layout.main')

@section('content')
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">Sincronización de Contenido</h1>
        <p class="hero-subtitle">Sincroniza tus archivos Markdown con la base de datos.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            @if(!isset($results))
                <div class="card border-0 shadow-sm rounded-4 overlay-hidden">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="icon-wrapper me-3 bg-warning-soft text-warning">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-0">Atención</h3>
                                <p class="text-muted mb-0">Este proceso actualizará la base de datos con el contenido de los archivos en <code>Content/</code>.</p>
                            </div>
                        </div>

                        <div class="alert alert-warning border-0 rounded-4 p-4 mb-4">
                            <p class="mb-0">Se recomienda tener una copia de seguridad del contenido de la base de datos si has realizado ediciones manuales desde el panel, ya que podrían ser sobrescritas por los archivos Markdown.</p>
                        </div>

                        <div class="d-grid mt-4">
                            <form action="/index.php?module=Chascarrillo&controller=Post&action=sync" method="POST">
                                <input type="hidden" name="confirm" value="1">
                                
                                <div class="form-check mb-4 p-3 border rounded-3 bg-light">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="rebuild" value="1" id="rebuildCheck">
                                    <label class="form-check-label fw-bold text-danger" for="rebuildCheck">
                                        <i class="fas fa-trash-alt me-1"></i> Reconstrucción Total
                                    </label>
                                    <div class="form-text small">
                                        Si marcas esta opción, se vaciará la base de datos de posts y páginas antes de importar los archivos MD. Útil para eliminar "enlaces fantasma" o contenido antiguo que ya no existe en tus archivos.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow-sm w-100">
                                    <i class="fas fa-sync me-2"></i> Iniciar Sincronización Ahora
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <div class="card border-0 shadow-sm rounded-4 overlay-hidden">
                    <div class="card-body p-5">
                        <div class="text-center mb-5">
                            @if($results['success'])
                                <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                                <h3 class="fw-bold">Sincronización Completada</h3>
                                <p class="text-muted">El proceso se ha realizado correctamente.</p>
                            @else
                                <i class="fas fa-times-circle text-danger fa-4x mb-3"></i>
                                <h3 class="fw-bold">Error en Sincronización</h3>
                                <p class="text-muted">{{ $results['error'] ?? 'Ocurrió un error inesperado durante el proceso.' }}</p>
                            @endif
                        </div>

                        <div class="list-group list-group-flush mb-4">
                            <div class="list-group-item border-0 d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="mb-0">Artículos (Posts)</h6>
                                    <small class="text-muted">Archivos procesados en Content/posts</small>
                                </div>
                                <span class="badge bg-primary rounded-pill">{{ $results['posts']['processed'] ?? 0 }}</span>
                            </div>
                            <div class="list-group-item border-0 d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="mb-0">Páginas</h6>
                                    <small class="text-muted">Archivos procesados en Content/pages</small>
                                </div>
                                <span class="badge bg-info rounded-pill">{{ $results['pages']['processed'] ?? 0 }}</span>
                            </div>
                            <div class="list-group-item border-0 d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="mb-0">Multimedia</h6>
                                    <small class="text-muted">Archivos sincronizados en uploads/</small>
                                </div>
                                <span class="badge bg-success rounded-pill">{{ $results['assets'] ?? 0 }}</span>
                            </div>
                        </div>

                        @if(!empty($results['posts']['errors']) || !empty($results['pages']['errors']))
                            <div class="alert alert-danger border-0 rounded-4 p-4 mt-4">
                                <h6 class="fw-bold"><i class="fas fa-bug me-2"></i> Errores encontrados:</h6>
                                <ul class="mb-0 small">
                                    @foreach($results['posts']['errors'] ?? [] as $error)
                                        <li>Post: {{ $error }}</li>
                                    @endforeach
                                    @foreach($results['pages']['errors'] ?? [] as $error)
                                        <li>Page: {{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="d-grid mt-4">
                            <a href="index.php?module=Chascarrillo&controller=Post" class="btn btn-outline-primary btn-lg rounded-pill">
                                <i class="fas fa-arrow-left me-2"></i> Volver a Gestión
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
