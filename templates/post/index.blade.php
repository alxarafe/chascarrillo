@extends('partial.layout.main')

@section('header_actions')
    <div class="d-flex gap-2">
        <a href="index.php?module=Chascarrillo&controller=Post&id=new" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-1"></i> Nuevo Chascarrillo
        </a>
        <button onclick="syncContent()" class="btn btn-outline-info rounded-pill">
            <i class="fas fa-sync me-1"></i> Sincronizar MD
        </button>
    </div>
@endsection

@section('content')
<div class="container-fluid py-4">
    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4 bg-light bg-opacity-50">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="module" value="Chascarrillo">
                <input type="hidden" name="controller" value="Post">
                
                <div class="col-md-3">
                    <label class="form-label small text-muted text-uppercase fw-bold">Estado</label>
                    <select name="filter_general_status" class="form-select border-0 shadow-sm" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <option value="published" {{ ($config['filters']['status'] ?? '') === 'published' || ($_GET['filter_general_status'] ?? '') === 'published' ? 'selected' : '' }}>Publicados</option>
                        <option value="scheduled" {{ ($config['filters']['status'] ?? '') === 'scheduled' || ($_GET['filter_general_status'] ?? '') === 'scheduled' ? 'selected' : '' }}>Programados</option>
                        <option value="draft" {{ ($config['filters']['status'] ?? '') === 'draft' || ($_GET['filter_general_status'] ?? '') === 'draft' ? 'selected' : '' }}>Borradores</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <!-- Space for search if needed -->
                </div>
                
                <div class="col-md-3 text-end">
                    <span class="text-muted small">Mostrando {{ count($posts) }} chascarrillos</span>
                </div>
            </form>
        </div>
    </div>

    <!-- Posts Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-white border-bottom">
                        <tr>
                            <th class="ps-4 py-3 text-muted small text-uppercase" style="width: 80px;">ID</th>
                            <th class="py-3 text-muted small text-uppercase">Título</th>
                            <th class="py-3 text-muted small text-uppercase">Slug</th>
                            <th class="py-3 text-muted small text-uppercase">Tipo</th>
                            <th class="py-3 text-muted small text-uppercase">Estado</th>
                            <th class="py-3 text-muted small text-uppercase">Publicación</th>
                            <th class="pe-4 py-3 text-end text-muted small text-uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $post)
                            <tr>
                                <td class="ps-4 text-muted small">#{{ $post->id }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $post->title }}</div>
                                    @if($post->in_menu)
                                        <span class="badge bg-light text-primary border border-primary-subtle rounded-pill" style="font-size: 0.65rem;">
                                            <i class="fas fa-bars me-1"></i> Menú
                                        </span>
                                    @endif
                                </td>
                                <td><code class="small text-muted">{{ $post->slug }}</code></td>
                                <td>
                                    @if($post->type === 'page')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">Página</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Post</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$post->is_published)
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Borrador</span>
                                    @elseif($post->published_at > date('Y-m-d H:i:s'))
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Programado</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Publicado</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ $post->published_at ? $post->published_at->format('d-m-Y H:i') : '-' }}
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="{{ $post->getUrl() }}" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Ver (Preview)">
                                            <i class="fas fa-eye text-info"></i>
                                        </a>
                                        <a href="index.php?module=Chascarrillo&controller=Post&id={{ $post->id }}" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Editar">
                                            <i class="fas fa-pen text-primary"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted mb-3">
                                        <i class="fas fa-folder-open fa-3x opacity-25"></i>
                                    </div>
                                    <p class="mb-0">No se encontraron chascarrillos.</p>
                                    <a href="index.php?module=Chascarrillo&controller=Post&id=new" class="btn btn-link">Crear el primero</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function syncContent() {
    if (!confirm('¿Seguro que quieres sincronizar el contenido desde archivos Markdown?')) return;
    
    fetch('index.php?module=Chascarrillo&controller=Post&action=sync&ajax=1')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error en la sincronización');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}
</script>
@endsection
