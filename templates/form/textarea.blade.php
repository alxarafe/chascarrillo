@php
    $name = $name ?? "data[" . ($field ?? "") . "]";
    $label = $label ?? "";
    $value = $value ?? "";
    $rows = $rows ?? 4;
    $required = !empty($required) ? 'required' : '';
    $readonly = !empty($readonly) ? 'readonly' : '';
@endphp
<div class="mb-3">
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id ?? $name }}" class="form-control" rows="{{ $rows }}" {{ $required }} {{ $readonly }}>{{ $value }}</textarea>
</div>
