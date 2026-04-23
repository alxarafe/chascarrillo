@props([
    'name',
    'label' => '',
    'value' => false
])

<div {{ $attributes->merge(['class' => 'form-group mb-3']) }}>
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $name }}" {{ $attributes->merge(['class' => 'form-select']) }}>
        <option value="1" @selected($value)>{{ \Alxarafe\Infrastructure\Lib\Trans::_('yes') }}</option>
        <option value="0" @selected(!$value)>{{ \Alxarafe\Infrastructure\Lib\Trans::_('no') }}</option>
    </select>
</div>
