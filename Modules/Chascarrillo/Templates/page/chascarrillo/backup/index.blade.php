@extends('partial.layout.main')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 20px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);">
                <div class="card-header bg-primary text-white p-4 border-0 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-archive fa-2x me-3"></i>
                        <div>
                            <h2 class="mb-0 fw-bold">Gestión de Copias de Seguridad</h2>
                            <p class="mb-0 opacity-75">Importación, exportación y sincronización de contenidos</p>
                        </div>
                    </div>
                    <a href="/" class="btn btn-light rounded-pill px-4 fw-bold">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Inicio
                    </a>
                </div>
                <div class="card-body p-5">
                    
                    <div class="row g-4">
                        <!-- Export Card -->
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 h-100 bg-light transition-hover" style="border: 2px dashed #dee2e6 !important;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                                        <i class="fas fa-download fa-lg"></i>
                                    </div>
                                    <h4 class="mb-0 fw-bold">Exportar Sitio</h4>
                                </div>
                                <p class="text-muted">Descarga un archivo ZIP con toda la estructura de <code>Content/</code> y el archivo <code>config.json</code>.</p>
                                <a href="index.php?module=Chascarrillo&controller=Backup&action=export" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">
                                    <i class="fas fa-file-download me-2"></i>Descargar Backup
                                </a>
                            </div>
                        </div>

                        <!-- Import Card -->
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 h-100 bg-light transition-hover" style="border: 2px dashed #dee2e6 !important;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                                        <i class="fas fa-upload fa-lg"></i>
                                    </div>
                                    <h4 class="mb-0 fw-bold">Importar Sitio</h4>
                                </div>
                                <p class="text-muted">Sube un archivo ZIP para restaurar la carpeta <code>Content/</code> y la configuración.</p>
                                <form action="index.php?module=Chascarrillo&controller=Backup&action=import" method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <input class="form-control form-control-sm rounded-pill" type="file" name="backup_zip" accept=".zip" required>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100 rounded-pill py-2 fw-bold">
                                        <i class="fas fa-file-upload me-2"></i>Subir e Importar
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-12 mt-5">
                            <h3 class="fw-bold mb-4 d-flex align-items-center">
                                <i class="fas fa-sync text-info me-3"></i>Sincronización Avanzada
                            </h3>
                        </div>

                        <!-- Reset DB from Content -->
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 h-100 border-danger border-opacity-25" style="background-color: #fff5f5;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle me-3">
                                        <i class="fas fa-database fa-lg"></i>
                                    </div>
                                    <h4 class="mb-0 fw-bold">Reset DB desde Content</h4>
                                </div>
                                <p class="text-muted">Elimina <strong>TODOS</strong> los registros de la base de datos (posts, medios, tags) y los vuelve a crear leyendo la carpeta <code>Content/</code>.</p>
                                <button onclick="confirmReset()" class="btn btn-outline-danger w-100 rounded-pill py-2 fw-bold">
                                    <i class="fas fa-trash-alt me-2"></i>Limpiar e Importar
                                </button>
                            </div>
                        </div>

                        <!-- Rebuild Content from DB -->
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 h-100 border-warning border-opacity-25" style="background-color: #fffaf0;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle me-3">
                                        <i class="fas fa-folder-open fa-lg"></i>
                                    </div>
                                    <h4 class="mb-0 fw-bold">Reconstruir Content</h4>
                                </div>
                                <p class="text-muted">Elimina los archivos actuales de <code>Content/</code> y genera nuevos archivos Markdown basados en lo que hay en la base de datos.</p>
                                <button onclick="confirmRebuild()" class="btn btn-outline-warning w-100 rounded-pill py-2 fw-bold">
                                    <i class="fas fa-tools me-2"></i>Generar Archivos MD
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmReset() {
    if (confirm('¿Estás seguro? Esta acción vaciará la base de datos por completo antes de importar desde los archivos.')) {
        window.location.href = 'index.php?module=Chascarrillo&controller=Backup&action=resetFromContent';
    }
}

function confirmRebuild() {
    if (confirm('¿Estás seguro? Se borrará el contenido actual de la carpeta Content y se generará uno nuevo desde la base de datos.')) {
        window.location.href = 'index.php?module=Chascarrillo&controller=Backup&action=rebuildContent';
    }
}
</script>

<style>
.transition-hover {
    transition: all 0.3s ease;
}
.transition-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    border-color: var(--bs-primary) !important;
}
</style>
@endsection
