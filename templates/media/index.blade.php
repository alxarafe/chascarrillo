@extends('partial.layout.main')

@section('header_actions')
    <div class="d-flex gap-2">
        <button onclick="syncMedia()" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-sync me-1"></i> Sincronizar Archivos
        </button>
    </div>
@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Biblioteca Multimedia</h4>
        <div class="text-muted small">
            {{ count($media) }} archivos encontrados
        </div>
    </div>

    <div class="row g-4">
        @forelse($media as $item)
            <div class="col-6 col-md-4 col-lg-3 col-xxl-2">
                <div class="card h-100 border-0 shadow-sm hover-lift overflow-hidden media-card">
                    <div class="position-relative">
                        @if($item->type === 'image')
                            <div class="ratio ratio-1x1 bg-light">
                                <img src="{{ $item->getUrl() }}" class="object-fit-cover w-100 h-100" alt="{{ $item->alt_text }}">
                            </div>
                        @else
                            <div class="ratio ratio-1x1 bg-dark d-flex align-items-center justify-content-center">
                                <i class="fas fa-video fa-3x text-white opacity-50"></i>
                            </div>
                        @endif
                        <div class="media-overlay">
                            <div class="d-flex gap-2">
                                <a href="index.php?module=Chascarrillo&controller=Media&id={{ $item->id }}" class="btn btn-sm btn-light rounded-circle" title="Editar Info">
                                    <i class="fas fa-pen text-primary"></i>
                                </a>
                                <button class="btn btn-sm btn-light rounded-circle" onclick="copyUrl('{{ $item->getUrl() }}')" title="Copiar URL">
                                    <i class="fas fa-link text-success"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="text-truncate small fw-bold" title="{{ $item->filename }}">
                            {{ $item->filename }}
                        </div>
                        <div class="text-muted" style="font-size: 0.7rem;">
                            {{ strtoupper($item->type) }} • {{ round($item->size / 1024, 1) }} KB
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 py-5 text-center">
                <div class="text-muted mb-3">
                    <i class="fas fa-images fa-4x opacity-25"></i>
                </div>
                <h5>La biblioteca está vacía</h5>
                <p>Usa el botón de sincronizar para buscar archivos en <code>Content/images</code>.</p>
            </div>
        @endforelse
    </div>
</div>

<script>
function syncMedia() {
    location.href = 'index.php?module=Chascarrillo&controller=Media&action=sync';
}

function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('URL copiada al portapapeles');
    });
}
</script>

<style>
    .media-card { transition: transform 0.2s, box-shadow 0.2s; border-radius: 10px; }
    .media-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .media-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .media-card:hover .media-overlay { opacity: 1; }
    .object-fit-cover { object-fit: cover; }
    .hover-lift:hover { transform: translateY(-3px); }
</style>
@endsection
