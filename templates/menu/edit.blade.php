@extends('partial.layout.main')

@section('content')
<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-bars me-2 text-primary"></i> 
                        {{ $recordId === 'new' ? 'Nuevo Menú' : 'Gestionar Menú: ' . ($data['name'] ?? '') }}
                    </h5>
                    <div>
                        <a href="{{ $me::url() }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form id="menu-edit-form" method="POST" action="{{ $me::url('save') }}">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="{{ $recordId }}">

                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del Menú</label>
                                <input type="text" name="data[name]" class="form-control" value="{{ $data['name'] ?? '' }}" required placeholder="Ej: Menú Principal">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Slug (Identificador)</label>
                                <input type="text" name="data[slug]" class="form-control font-monospace" value="{{ $data['slug'] ?? '' }}" required placeholder="ej: header-menu">
                                <small class="text-muted">Se usa en el código para cargar el menú.</small>
                            </div>
                        </div>

                        <div class="border-top pt-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0">Elementos del Menú</h6>
                                <button type="button" class="btn btn-sm btn-success rounded-pill" onclick="addItem()">
                                    <i class="fas fa-plus me-1"></i> Añadir Elemento
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle" id="menu-items-table">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 50px;">Mover</th>
                                            <th>Etiqueta</th>
                                            <th>URL / Ruta</th>
                                            <th style="width: 100px;">Icono</th>
                                            <th style="width: 80px;">Orden</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="menu-items-body">
                                        @php $i = 0; @endphp
                                        @foreach($data['items'] ?? [] as $item)
                                            <tr class="menu-item-row">
                                                <td class="text-center cursor-move"><i class="fas fa-grip-vertical text-muted"></i></td>
                                                <td>
                                                    <input type="hidden" name="data[items][{{ $i }}][id]" value="{{ $item['id'] ?? '' }}">
                                                    <input type="text" name="data[items][{{ $i }}][label]" class="form-control form-control-sm" value="{{ $item['label'] ?? '' }}" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="data[items][{{ $i }}][url]" class="form-control form-control-sm" value="{{ $item['url'] ?? '' }}" placeholder="Ej: /blog o https://...">
                                                </td>
                                                <td>
                                                    <input type="text" name="data[items][{{ $i }}][icon]" class="form-control form-control-sm" value="{{ $item['icon'] ?? '' }}" placeholder="fas fa-link">
                                                </td>
                                                <td>
                                                    <input type="number" name="data[items][{{ $i }}][order]" class="form-control form-control-sm" value="{{ $item['order'] ?? $i }}">
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('tr').remove()">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            @php $i++; @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="border-top mt-4 pt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                                <i class="fas fa-save me-2"></i> Guardar Menú
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let itemCount = {{ count($data['items'] ?? []) }};

function addItem() {
    const tbody = document.getElementById('menu-items-body');
    const tr = document.createElement('tr');
    tr.className = 'menu-item-row';
    tr.innerHTML = `
        <td class="text-center cursor-move"><i class="fas fa grip-vertical text-muted"></i></td>
        <td>
            <input type="hidden" name="data[items][${itemCount}][id]" value="">
            <input type="text" name="data[items][${itemCount}][label]" class="form-control form-control-sm" value="" required placeholder="Nueva Opción">
        </td>
        <td>
            <input type="text" name="data[items][${itemCount}][url]" class="form-control form-control-sm" value="" placeholder="Ej: /slug">
        </td>
        <td>
            <input type="text" name="data[items][${itemCount}][icon]" class="form-control form-control-sm" placeholder="fas fa-link">
        </td>
        <td>
            <input type="number" name="data[items][${itemCount}][order]" class="form-control form-control-sm" value="${itemCount}">
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('tr').remove()">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    itemCount++;
}
</script>

<style>
    .cursor-move { cursor: move; }
    .card { border-radius: 12px; }
    .form-control-sm { border-radius: 6px; }
    .menu-item-row:hover { background-color: rgba(13, 110, 253, 0.02); }
</style>
@endsection
