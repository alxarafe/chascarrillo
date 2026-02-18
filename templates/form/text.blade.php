@php
    $name = $name ?? "data[" . ($field ?? "") . "]";
    $label = $label ?? "";
    $value = $value ?? "";
    $attributes = $attributes ?? [];
    if (!empty($required)) $attributes['required'] = 'required';
    if (!empty($readonly)) $attributes['readonly'] = 'readonly';
@endphp
@include('form.input', [
    'name' => $name,
    'label' => $label,
    'value' => $value,
    'type' => $type ?? 'text',
    'attributes' => $attributes
])
