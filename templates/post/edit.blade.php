@extends('partial.layout.main')

@section('content')
<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-edit me-2 text-primary"></i> 
                        {{ $recordId === 'new' ? 'Nuevo Chascarrillo' : 'Editar Chascarrillo' }}
                    </h5>
                    <div>
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
                                    @include('form.text', $fields['title']->jsonSerialize())
                                </div>
                                <div class="mb-3">
                                    @include('form.textarea', array_merge($fields['content']->jsonSerialize(), ['rows' => 20]))
                                </div>
                            </div>

                            <!-- Right Column: Settings & SEO -->
                            <div class="col-lg-4">
                                <div class="card bg-light border-0 mb-4">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Publicación</h6>
                                        <div class="mb-3">
                                            @include('form.text', $fields['slug']->jsonSerialize())
                                        </div>
                                        <div class="mb-3">
                                            @include('form.boolean', $fields['is_published']->jsonSerialize())
                                        </div>
                                        <div class="mb-3">
                                            @include('form.datetime', $fields['published_at']->jsonSerialize())
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
                                            @include('form.text', $fields['meta_title']->jsonSerialize())
                                        </div>
                                        <div class="mb-3">
                                            @include('form.textarea', array_merge($fields['meta_description']->jsonSerialize(), ['rows' => 3]))
                                        </div>
                                        <div class="mb-3">
                                            @include('form.text', $fields['meta_keywords']->jsonSerialize())
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
            document.getElementById('markdown-code').value = '![' + document.getElementById('fields[title]').value + '](' + data.url + ')';
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
