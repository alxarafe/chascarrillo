@extends('partial.layout.main')

@section('header_actions')
    <div class="d-flex gap-2">
        <a href="index.php?module=Chascarrillo&controller=PageAdmin&id=new" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-1"></i> Nueva Página
        </a>
        <button onclick="syncContent()" class="btn btn-outline-info rounded-pill">
            <i class="fas fa-sync me-1"></i> Sincronizar MD
        </button>
    </div>
@endsection

@section('content')
<div class="container-fluid py-4">
    <!-- Pages Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="mb-0 fw-bold">Páginas Estáticas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 py-3 text-muted small text-uppercase" style="width: 80px;">ID</th>
                            <th class="py-3 text-muted small text-uppercase">Título</th>
                            <th class="py-3 text-muted small text-uppercase">Slug</th>
                            <th class="py-3 text-muted small text-uppercase">Menú</th>
                            <th class="py-3 text-muted small text-uppercase">Estado</th>
                            <th class="pe-4 py-3 text-end text-muted small text-uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $page)
                            <tr>
                                <td class="ps-4 text-muted small">#{{ $page->id }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $page->title }}</div>
                                </td>
                                <td><code class="small text-muted">/{{ $page->slug }}</code></td>
                                <td>
                                    @if($page->in_menu)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                            <i class="fas fa-check me-1"></i> Visible ({{ $page->menu_order }})
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border border-light-subtle rounded-pill">Oculta</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$page->is_published)
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Borrador</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Publicada</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="index.php?module=Chascarrillo&controller=PageAdmin&id={{ $page->id }}" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Editar">
                                        <i class="fas fa-pen text-primary"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted mb-3">
                                        <i class="fas fa-file fa-3x opacity-25"></i>
                                    </div>
                                    <p class="mb-0">No se encontraron páginas estáticas.</p>
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
