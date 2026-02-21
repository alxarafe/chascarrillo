@extends('partial.layout.main')

@section('content')
<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-file-alt me-2 text-primary"></i> 
                        {{ $recordId === 'new' ? 'Nueva Página' : 'Editar Página' }}
                    </h5>
                    <div>
                        <a href="{{ $me::url() }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form id="page-edit-form" method="POST" action="{{ $me::url('save') }}">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="{{ $recordId }}">
                        <input type="hidden" name="data[type]" value="page">

                        <div class="row">
                            <!-- Left Column: Content -->
                            <div class="col-lg-8">
                                <div class="mb-3">
                                    @include('form.text', array_merge($fields['title']->jsonSerialize(), ['value' => $data['title'] ?? '']))
                                </div>

                                <!-- Tabs for Markdown/Preview -->
                                <ul class="nav nav-pills mb-3" id="pageContentTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-pane" type="button" role="tab">
                                            <i class="fas fa-code me-1"></i> Markdown
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview-pane" type="button" role="tab" onclick="updatePreview()">
                                            <i class="fas fa-eye me-1"></i> Visualización
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content" id="pageContentTabsContent">
                                    <!-- Markdown Editor -->
                                    <div class="tab-pane fade show active" id="edit-pane" role="tabpanel">
                                        <div class="mb-3">
                                            @include('form.textarea', array_merge($fields['content']->jsonSerialize(), [
                                                'rows' => 20,
                                                'id' => 'post_content_editor',
                                                'value' => $data['content'] ?? ''
                                            ]))
                                        </div>
                                    </div>
                                    
                                    <!-- HTML Preview -->
                                    <div class="tab-pane fade" id="preview-pane" role="tabpanel">
                                        <div id="markdown-preview" class="border rounded bg-white p-4 shadow-sm markdown-body" style="min-height: 500px; max-height: 800px; overflow-y: auto;">
                                            <div class="text-center py-5">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Cargando...</span>
                                                </div>
                                                <p class="mt-2 text-muted">Generando previsualización...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Settings & SEO -->
                            <div class="col-lg-4">
                                <div class="card bg-light border-0 mb-4">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Publicación</h6>
                                        <div class="mb-3">
                                            @include('form.text', array_merge($fields['slug']->jsonSerialize(), ['value' => $data['slug'] ?? '']))
                                        </div>
                                        <div class="mb-3">
                                            @include('form.boolean', array_merge($fields['is_published']->jsonSerialize(), ['value' => $data['is_published'] ?? true]))
                                        </div>
                                    </div>
                                </div>

                                <div class="card bg-light border-0 mb-4">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Menú de Navegación</h6>
                                        <div class="mb-3">
                                            @include('form.boolean', array_merge($fields['in_menu']->jsonSerialize(), ['value' => $data['in_menu'] ?? false]))
                                        </div>
                                        <div class="mb-3">
                                            @include('form.integer', array_merge($fields['menu_order']->jsonSerialize(), ['value' => $data['menu_order'] ?? 0]))
                                        </div>
                                        <small class="text-muted">Determina si la página aparece en el menú superior público y en qué orden.</small>
                                    </div>
                                </div>

                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">SEO (Opcional)</h6>
                                        <div class="mb-3">
                                            @include('form.text', array_merge($fields['meta_title']->jsonSerialize(), ['value' => $data['meta_title'] ?? '']))
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Imagen Destacada (URL)</label>
                                            <div class="input-group">
                                                <input type="text" name="data[featured_image]" class="form-control" value="{{ $data['featured_image'] ?? '' }}" id="featured_image_input">
                                                <button class="btn btn-outline-secondary" type="button" onclick="showMediaSelector()">
                                                    <i class="fas fa-images"></i>
                                                </button>
                                            </div>
                                            @if(!empty($data['featured_image']))
                                                <img src="{{ $data['featured_image'] }}" class="img-thumbnail mt-2" style="max-height: 100px;">
                                            @endif
                                        </div>
                                        <div class="mb-3">
                                            @include('form.textarea', array_merge($fields['meta_description']->jsonSerialize(), ['rows' => 3, 'value' => $data['meta_description'] ?? '']))
                                        </div>
                                        <div class="mb-3">
                                            @include('form.text', array_merge($fields['meta_keywords']->jsonSerialize(), ['value' => $data['meta_keywords'] ?? '']))
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-top mt-4 pt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                                <i class="fas fa-save me-2"></i> Guardar Página
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updatePreview() {
    const content = document.getElementById('post_content_editor').value;
    const previewContainer = document.getElementById('markdown-preview');
    
    previewContainer.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2 text-muted">Generando previsualización...</p>
        </div>
    `;

    fetch('index.php?module=Chascarrillo&controller=Post&ajax=render_markdown', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'content=' + encodeURIComponent(content)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            previewContainer.innerHTML = data.html;
        } else {
            previewContainer.innerHTML = '<div class="alert alert-danger">Error al generar la previsualización</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        previewContainer.innerHTML = '<div class="alert alert-danger">Error de conexión al generar la previsualización</div>';
    });
}
</script>

<style>
    .card { border-radius: 12px; }
    .card-header { border-top-left-radius: 12px !important; border-top-right-radius: 12px !important; }
    .bg-light { background-color: #f8f9fa !important; }
    .form-label { font-weight: 600; color: #444; margin-bottom: 0.4rem; }
    .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1); border-color: #86b7fe; }
</style>
@endsection
