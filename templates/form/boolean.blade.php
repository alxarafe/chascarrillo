@props([
    'field' => null,
    'name' => null,
    'label' => '',
    'value' => false,
])

@php
    $name = $name ?? ($field ? "data[" . $field . "]" : '');
@endphp

<div {{ $attributes->merge(['class' => 'form-check mb-3']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="{{ $name }}" @checked($value)>
    <label class="form-check-label" for="{{ $name }}">
        {{ $label }}
    </label>
</div>

