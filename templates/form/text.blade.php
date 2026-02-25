@props([
    'field' => null,
    'name' => null,
    'label' => '',
    'value' => '',
    'help' => '',
    'actions' => [],
])

@php
    $name = $name ?? ($field ? "data[" . $field . "]" : '');
@endphp

<x-form.input 
    type="text" 
    :name="$name" 
    :label="$label" 
    :value="$value" 
    :help="$help" 
    :actions="$actions" 
    {{ $attributes }} 
/>

