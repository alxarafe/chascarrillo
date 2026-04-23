@props([
    'name',
    'label' => '',
    'value' => '',
    'values' => [],
    'class' => ''
])

<div {{ $attributes->merge(['class' => 'form-group mb-3']) }}>
    @if($label)
        <label for="{{ $name }}" class="form-label cyberpunk-label">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $name }}" {{ $attributes->merge(['class' => 'form-select cyber-select ' . $class]) }}>
        @foreach($values as $option => $text)
            <option value="{{ $option }}" @selected((string)$value === (string)$option)>{{ $text }}</option>
        @endforeach
    </select>
</div>
