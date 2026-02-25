@props([
    'field' => null,
    'name' => null,
    'label' => '',
    'value' => '',
    'rows' => 4,
    'help' => '',
])

@php
    $name = $name ?? ($field ? "data[" . $field . "]" : '');
@endphp

<div class="mb-3">
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <textarea name="{{ $name }}" 
              id="{{ $attributes->get('id') ?? $name }}" 
              {{ $attributes->merge(['class' => 'form-control', 'rows' => $rows]) }}>{{ $value }}</textarea>
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>

