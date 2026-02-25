@props([
    'field' => null,
    'name' => null,
    'label' => '',
    'value' => '',
    'help' => '',
])

@php
    $name = $name ?? ($field ? "data[" . $field . "]" : '');
    
    // Format value for datetime-local input (YYYY-MM-DDTHH:MM)
    if ($value) {
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d\TH:i');
        } elseif (is_string($value)) {
            $value = date('Y-m-d\TH:i', strtotime($value));
        }
    }
@endphp

<x-form.input 
    type="datetime-local" 
    :name="$name" 
    :label="$label" 
    :value="$value" 
    :help="$help" 
    {{ $attributes }} 
/>

