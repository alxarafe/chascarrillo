@php
    $languagesData = \Alxarafe\Infrastructure\Lib\Trans::getAvailableLanguagesWithFlags();

    $cookieLang = $_COOKIE['alx_lang'] ?? null;
    $userLang = \Alxarafe\Infrastructure\Auth\Auth::$user->language ?? null;

    $configLang = null;
    try {
        $configLang = \Alxarafe\Infrastructure\Persistence\Config::getConfig()->main->language ?? null;
    } catch (\Throwable $e) {}

    $currentLang = $cookieLang ?? $userLang ?? $configLang ?? \Alxarafe\Infrastructure\Lib\Trans::FALLBACK_LANG;
    $isUserSelection = !empty($cookieLang) || !empty($userLang);
    $currentFlag = $languagesData[$currentLang]['flag'] ?? 'un';
@endphp

<a class="cyber-retro-button" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ \Alxarafe\Infrastructure\Lib\Trans::_('select_language') }}">
    <div class="cyber-button-inner">
        @if($isUserSelection && $currentFlag !== 'un')
            <span class="fi fi-{{ $currentFlag }} rounded-1 shadow-sm" style="width: 1.8rem; height: 1.4rem; flex-shrink: 0;"></span>
        @else
            <i class="cyber-retro-icon fas fa-globe fa-2x cyber-icon"></i>
        @endif
    </div>
    <span class="cyber-pixel pixel-tl"></span>
    <span class="cyber-pixel pixel-tr"></span>
    <span class="cyber-pixel pixel-bl"></span>
    <span class="cyber-pixel pixel-br"></span>
</a>

<ul class="dropdown-menu dropdown-menu-end shadow animate__animated animate__fadeInFast">
    <li class="dropdown-header text-uppercase small fw-bold text-primary">{{ \Alxarafe\Infrastructure\Lib\Trans::_('available_languages') }}</li>
    <li><hr class="dropdown-divider"></li>
    @foreach($languagesData as $code => $lang)
        <li>
            <a class="dropdown-item d-flex align-items-center py-2 {{ $currentLang === $code ? 'active' : '' }}"
               href="/index.php?module=Admin&controller=Auth&action=setLang&lang={{ $code }}">
                <span class="fi fi-{{ $lang['flag'] ?? 'un' }} me-3 shadow-sm rounded-1" style="width: 1.5rem; height: 1.1rem;"></span>
                <span>{{ $lang['name'] }}</span>
            </a>
        </li>
    @endforeach
</ul>
