@extends('layout.public')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
        <div class="col-12">
            <!-- Header moved to Top Bar -->
        </div>
        </div>
    </div>

    @if(!empty($alerts))
        @include('partial.alerts')
    @endif

    <form method="POST" enctype="multipart/form-data" action="index.php?module=Admin&controller=Profile&action=save">
        <input type="hidden" name="action" value="save">
        
        <div class="row">
            @if(isset($panels) && is_array($panels))
                @foreach($panels as $panel)
                
                @php
                    $panelFields = $panel->getFields();
                    $panelOpts = $panel->getOptions();
                    $colClass = $panelOpts['col'] ?? 'col-12';
                @endphp

                <div class="{{ $colClass }} mb-4">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">{{ $panel->getLabel() }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                            @foreach($panelFields as $field)
                                @php
                                    $type = $field->getType();
                                    $component = $field->getComponent(); // 'text', 'select', 'static', etc.
                                    $label = $field->getLabel();
                                    $name = $field->getField();
                                    
                                    // Extract attributes
                                    $opts = $field->getOptions(); 
                                    $attributes = $opts['options'] ?? $opts;
                                    
                                    $value = $attributes['value'] ?? '';
                                    if(isset($_POST[$name])) $value = $_POST[$name];
                                    
                                    $col = $attributes['col'] ?? 'col-12';
                                    $required = !empty($attributes['required']);
                                    $disabled = !empty($attributes['disabled']);
                                    $placeholder = $attributes['placeholder'] ?? '';
                                    $help = $attributes['help'] ?? '';
                                    
                                    $actions = $field->getActions();
                                @endphp

                                    {{-- STATIC TEXT (Content stored in Label) --}}
                                    @if($component === 'static' || $component === 'static_text')
                                        <div class="mb-3">
                                            {!! $label !!}
                                        </div>
                                    @else
                                        <label for="{{ $name }}" class="form-label">
                                            {{ $label }}
                                            @if($required) <span class="text-danger">*</span> @endif
                                        </label>
                                        
                                        <div class="@if(!empty($actions)) input-group @endif">
                                        
                                            {{-- SELECT / SELECT2 --}}
                                            @if($component === 'select' || $component === 'select2')
                                                <select name="{{ $name }}" id="{{ $name }}" 
                                                        class="form-select @if($component === 'select2') select2 @endif"
                                                        @if($required) required @endif
                                                        @if($disabled) disabled @endif>
                                                    @foreach($attributes['values'] ?? [] as $optVal => $optLabel)
                                                        <option value="{{ $optVal }}" @selected((string)$value === (string)$optVal)>
                                                            {{ $optLabel }}
                                                        </option>
                                                    @endforeach
                                                </select>
    
                                            {{-- BOOLEAN / CHECKBOX --}}
                                            @elseif($component === 'boolean' || $component === 'checkbox')
                                                <div class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" name="{{ $name }}" id="{{ $name }}" value="1"
                                                           @checked($value) @if($disabled) disabled @endif>
                                                    <label class="form-check-label" for="{{ $name }}">{{ $attributes['text_enabled'] ?? 'Active' }}</label>
                                                </div>
    
                                            {{-- TEXT / PASSWORD / FILE / EMAIL --}}
                                            @else
                                                <input type="{{ $attributes['type'] ?? 'text' }}" 
                                                       name="{{ $name }}" id="{{ $name }}"
                                                       class="form-control"
                                                       value="{{ $value }}"
                                                       placeholder="{{ $placeholder }}"
                                                       @if($required) required @endif
                                                       @if($disabled) disabled @endif
                                                       @if(isset($attributes['accept'])) accept="{{ $attributes['accept'] }}" @endif
                                                >
                                            @endif
                                            
                                            {{-- ACTIONS (Buttons) --}}
                                            @foreach($actions as $action)
                                                <button type="button" 
                                                        class="btn {{ $action['class'] ?? 'btn-outline-secondary' }}" 
                                                        onclick="{{ $action['onclick'] }}"
                                                        title="{{ $action['title'] }}">
                                                    <i class="{{ $action['icon'] }}"></i>
                                                </button>
                                            @endforeach
                                            
                                        </div>
                                        @if($help)
                                            <div class="form-text">{{ $help }}</div>
                                        @endif
                                    @endif
                            @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        <div class="row mt-3 mb-5">
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> {{ \Alxarafe\Lib\Trans::_('save') }}
                </button>
                <a href="index.php?module=Admin&controller=Dashboard" class="btn btn-secondary">
                    {{ \Alxarafe\Lib\Trans::_('cancel') }}
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
