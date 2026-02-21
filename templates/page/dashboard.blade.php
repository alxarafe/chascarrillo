@extends('partial.layout.main')

@section('content')
<div class="container-fluid py-4">
    <div class="row align-items-center mb-5">
        <div class="col">
            <h1 class="h3 fw-800 mb-1">¡Bienvenido, {{ \Alxarafe\Lib\Auth::$user->name }}!</h1>
            <p class="text-muted mb-0">Aquí tienes un resumen de lo que está ocurriendo en tu blog.</p>
        </div>
        <div class="col-auto">
            <a href="index.php?module=Chascarrillo&controller=Post&action=edit&id=new" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-plus me-2"></i> Nuevo Chascarrillo
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                            <i class="fas fa-newspaper fa-lg"></i>
                        </div>
                        <h6 class="card-subtitle text-muted fw-bold text-uppercase small" style="letter-spacing: 0.1em;">Chascarrillos</h6>
                    </div>
                    <div class="h2 fw-800 mb-0">{{ $stats['posts'] }}</div>
                    <div class="text-success small mt-2">
                        <i class="fas fa-check-circle me-1"></i> {{ $stats['published_posts'] }} publicados
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded-3 me-3">
                            <i class="fas fa-file-alt fa-lg"></i>
                        </div>
                        <h6 class="card-subtitle text-muted fw-bold text-uppercase small" style="letter-spacing: 0.1em;">Páginas</h6>
                    </div>
                    <div class="h2 fw-800 mb-0">{{ $stats['pages'] }}</div>
                    <p class="text-muted small mt-2 mb-0">Contenido estático</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 me-3">
                            <i class="fas fa-tags fa-lg"></i>
                        </div>
                        <h6 class="card-subtitle text-muted fw-bold text-uppercase small" style="letter-spacing: 0.1em;">Etiquetas</h6>
                    </div>
                    <div class="h2 fw-800 mb-0">{{ $stats['tags'] }}</div>
                    <p class="text-muted small mt-2 mb-0">Categorización activa</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-indigo bg-opacity-10 text-indigo p-3 rounded-3 me-3" style="color: #6610f2;">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                        <h6 class="card-subtitle text-muted fw-bold text-uppercase small" style="letter-spacing: 0.1em;">Administradores</h6>
                    </div>
                    <div class="h2 fw-800 mb-0">{{ \CoreModules\Admin\Model\User::count() }}</div>
                    <p class="text-muted small mt-2 mb-0">Usuarios con acceso</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Posts -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="fw-800 mb-0">Últimos Chascarrillos</h5>
                        <a href="index.php?module=Chascarrillo&controller=Post&action=index" class="btn btn-sm btn-light rounded-pill px-3">Ver todos</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light bg-opacity-50">
                                <tr>
                                    <th class="border-0 px-4 py-3">Título</th>
                                    <th class="border-0 py-3">Publicación</th>
                                    <th class="border-0 py-3">Estado</th>
                                    <th class="border-0 px-4 py-3 text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentPosts as $post)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="fw-bold text-secondary">{{ $post->title }}</div>
                                        <div class="small text-muted">{{ $post->slug }}</div>
                                    </td>
                                    <td class="py-3">
                                        {{ $post->published_at ? $post->published_at->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="py-3">
                                        @if($post->is_published)
                                            <span class="badge rounded-pill bg-success bg-opacity-10 text-success p-2 px-3">Publicado</span>
                                        @else
                                            <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning p-2 px-3">Borrador</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <a href="index.php?module=Chascarrillo&controller=Post&action=edit&id={{ $post->id }}" class="btn btn-sm btn-icon btn-light rounded-circle" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ $post->getUrl() }}" target="_blank" class="btn btn-sm btn-icon btn-light rounded-circle" title="Ver">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Tips / Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary text-white overflow-hidden position-relative">
                <div class="card-body p-4 position-relative" style="z-index: 2;">
                    <h5 class="fw-800 mb-3">Sync Tips</h5>
                    <p class="small mb-4">¿Has editado los archivos de Markdown manualmente? No olvides sincronizar para aplicar los cambios.</p>
                    <a href="index.php?module=Chascarrillo&controller=Post&action=sync" class="btn btn-light btn-sm rounded-pill px-4 fw-bold">
                        Sincronizar ahora
                    </a>
                </div>
                <i class="fas fa-sync-alt position-absolute" style="bottom: -20px; right: -20px; font-size: 150px; opacity: 0.1; transform: rotate(-15deg);"></i>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-transparent border-0 p-4">
                    <h5 class="fw-800 mb-0">Acciones Rápidas</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <div class="d-grid gap-2">
                        <a href="index.php?module=Admin&controller=Config" class="btn btn-outline-secondary border-0 text-start px-3 py-2 rounded-3 hover-bg-light">
                            <i class="fas fa-cogs me-3"></i> Configuración Global
                        </a>
                        <a href="index.php?module=Admin&controller=User" class="btn btn-outline-secondary border-0 text-start px-3 py-2 rounded-3 hover-bg-light">
                            <i class="fas fa-user-friends me-3"></i> Gestionar Usuarios
                        </a>
                        <a href="index.php?module=Chascarrillo&controller=Tag" class="btn btn-outline-secondary border-0 text-start px-3 py-2 rounded-3 hover-bg-light">
                            <i class="fas fa-tags me-3"></i> Categorías
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-800 { font-weight: 800; }
    .bg-indigo { background-color: #6610f2; }
    .bg-primary-soft { background-color: rgba(37, 99, 235, 0.1); }
    .btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .hover-bg-light:hover { background-color: #f8fafc; }
</style>
@endsection
