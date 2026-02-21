@extends('partial.layout.main')

@section('content')
@if(isset($page))
<div class="hero-section">
    <div class="container">
        <div class="page-content">
            {!! $page->getRenderedContent() !!}
        </div>
    </div>
</div>
@else
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">{{ \Alxarafe\Lib\Trans::_('hero_title') }}</h1>
        <p class="hero-subtitle">
            {{ \Alxarafe\Lib\Trans::_('hero_subtitle') }}<br>
            <small class="text-muted">{{ \Alxarafe\Lib\Trans::_('no_cookies') }}</small>
        </p>
    </div>
</div>
@endif

@endsection
