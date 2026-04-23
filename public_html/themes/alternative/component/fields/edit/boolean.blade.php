@props([
    'field' => '',
    'label' => '',
    'value' => 0,
    'required' => false,
    'readonly' => false
])

<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
    <select class="form-select pastel-select" id="{{ $field }}" name="{{ $field }}" @required($required) @disabled($readonly)>
        <option value="1" @selected($value == 1)>{{ \Alxarafe\Infrastructure\Lib\Trans::_('yes') }}</option>
        <option value="0" @selected($value == 0)>{{ \Alxarafe\Infrastructure\Lib\Trans::_('no') }}</option>
    </select>
</div>
