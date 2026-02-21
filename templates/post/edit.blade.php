@extends('partial.layout.main')

@section('content')
<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-edit me-2 text-primary"></i> 
                        {{ $recordId === 'new' ? 'Nuevo Chascarrillo' : 'Editar Chascarrillo' }}
                    </h5>
                    <div class="d-flex gap-2">
                        @if($recordId !== 'new')
                            <a href="{{ \Modules\Chascarrillo\Model\Post::find($recordId)?->getUrl() }}" target="_blank" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-eye me-1"></i> Ver post
                            </a>
                        @endif
                        <a href="{{ $me::url() }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form id="post-edit-form" method="POST" action="{{ $me::url('save') }}">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="{{ $recordId }}">

                        <div class="row">
                            <!-- Left Column: Content -->
                            <div class="col-lg-8">
                                <div class="mb-3">
                                    @include('form.text', array_merge($fields['title']->jsonSerialize(), ['value' => $data['title'] ?? '']))
                                </div>

                                <!-- Tabs for Markdown/Preview -->
                                <ul class="nav nav-pills mb-3" id="postContentTabs" role="tablist">
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

                                <div class="tab-content" id="postContentTabsContent">
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
                                            @include('form.boolean', array_merge($fields['is_published']->jsonSerialize(), ['value' => $data['is_published'] ?? false]))
                                        </div>
                                        <div class="mb-3">
                                            @include('form.datetime', array_merge($fields['published_at']->jsonSerialize(), ['value' => $data['published_at'] ?? '']))
                                        </div>
                                    </div>
                                </div>

                                <div class="card bg-light border-0 mb-4">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Imagen Destacada</h6>
                                        <div id="image-preview-container" class="mb-3 {{ empty($data['featured_image']) ? 'd-none' : '' }}">
                                            <img id="featured-image-preview" src="{{ $data['featured_image'] ?? '' }}" class="img-fluid rounded border shadow-sm" style="max-height: 200px; width: 100%; object-fit: cover;">
                                        </div>
                                        <div class="input-group mb-2">
                                            <input type="text" name="data[featured_image]" id="featured_image_input" class="form-control" value="{{ $data['featured_image'] ?? '' }}" placeholder="URL de la imagen...">
                                            <button class="btn btn-primary" type="button" onclick="document.getElementById('image_upload_input').click()">
                                                <i class="fas fa-upload"></i>
                                            </button>
                                        </div>
                                        <input type="file" id="image_upload_input" class="d-none" accept="image/*" onchange="uploadFeaturedImage(this)">
                                        <div id="markdown-copy-container" class="mt-2 d-none">
                                            <label class="small text-muted mb-1">Copia esto al contenido:</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" id="markdown-code" class="form-control font-monospace" readonly>
                                                <button class="btn btn-outline-primary" type="button" onclick="copyMarkdownCode()">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-2">Puedes pegar una URL o subir un archivo.</small>
                                    </div>
                                </div>

                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">SEO (Opcional)</h6>
                                        <div class="mb-3">
                                            @include('form.text', array_merge($fields['meta_title']->jsonSerialize(), ['value' => $data['meta_title'] ?? '']))
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
                                <i class="fas fa-save me-2"></i> Guardar Chascarrillo
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
    const content = document.querySelector('textarea[name="data[content]"]').value;
    const previewContainer = document.getElementById('markdown-preview');
    
    // Show loading
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

function uploadFeaturedImage(input) {
    if (!input.files || !input.files[0]) return;

    const formData = new FormData();
    formData.append('file', input.files[0]);

    // Show loading state
    const btn = input.previousElementSibling.lastElementChild;
    const originalIcon = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetch('index.php?module=Chascarrillo&controller=Post&ajax=upload_image', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('featured_image_input').value = data.url;
            document.getElementById('featured-image-preview').src = data.url;
            document.getElementById('image-preview-container').classList.remove('d-none');
            
            // Generate Markdown code for easy copy-paste
            document.getElementById('markdown-code').value = '![' + document.getElementById('data[title]').value + '](' + data.url + ')';
            document.getElementById('markdown-copy-container').classList.remove('d-none');

            // Trigger Change for autosave frameworks if any
            const event = new Event('change');
            document.getElementById('featured_image_input').dispatchEvent(event);
        } else {
            alert('Error al subir imagen: ' + (data.message || 'Desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error en la conexión al subir imagen.');
    })
    .finally(() => {
        btn.innerHTML = originalIcon;
        btn.disabled = false;
        input.value = ''; // Reset input
    });
}

function copyMarkdownCode() {
    const input = document.getElementById('markdown-code');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    
    // Simple visual feedback
    const btn = event.currentTarget;
    const originalIcon = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    setTimeout(() => { btn.innerHTML = originalIcon; }, 2000);
}

// Sync preview on manual URL change
document.getElementById('featured_image_input').addEventListener('input', function() {
    const preview = document.getElementById('featured-image-preview');
    const container = document.getElementById('image-preview-container');
    if (this.value) {
        preview.src = this.value;
        container.classList.remove('d-none');
    } else {
        container.classList.add('d-none');
    }
});
</script>

<style>
    .card { border-radius: 12px; }
    .card-header { border-top-left-radius: 12px !important; border-top-right-radius: 12px !important; }
    .bg-light { background-color: #f8f9fa !important; }
    .form-label { font-weight: 600; color: #444; margin-bottom: 0.4rem; }
    .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1); border-color: #86b7fe; }
</style>
@endsection
