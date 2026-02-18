@php
    $name = $name ?? "data[" . ($field ?? "") . "]";
    $label = $label ?? "";
    $value = $value ?? "";
    
    // Format value for datetime-local input (YYYY-MM-DDTHH:MM)
    if ($value) {
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d\TH:i');
        } elseif (is_string($value)) {
            $value = date('Y-m-d\TH:i', strtotime($value));
        }
    }
@endphp

<div class="mb-3">
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <input type="datetime-local" name="{{ $name }}" id="{{ $name }}" class="form-control" value="{{ $value }}">
</div>
