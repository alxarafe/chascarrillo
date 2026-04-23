<!-- Cyberpunk Vertical Right Bar -->
<div class="cyber-rightbar d-flex flex-column align-items-center">
    <div class="cyber-scanline-v"></div>
    
    <!-- User Avatar / Profile with Hover Menu -->
    @if(\Alxarafe\Infrastructure\Auth\Auth::$user)
        <div class="mb-4 mt-3 text-center group-hover-glow position-relative">
            <a href="index.php?module=Admin&controller=Profile" class="text-decoration-none">
                @if(!empty(\Alxarafe\Infrastructure\Auth\Auth::$user->avatar) && file_exists(\Alxarafe\Infrastructure\Persistence\Config::getPublicRoot() . '/' . \Alxarafe\Infrastructure\Auth\Auth::$user->avatar))
                    <img src="{{ \Alxarafe\Infrastructure\Auth\Auth::$user->avatar }}" class="cyber-avatar rounded-circle border border-primary" style="width: 48px; height: 48px; object-fit: cover;">
                @else
                    <div class="cyber-avatar-placeholder rounded-circle border border-info d-flex align-items-center justify-content-center mx-auto" style="width: 48px; height: 48px; background: #000;">
                        <i class="fas fa-user cyber-icon text-info fa-lg"></i>
                    </div>
                @endif
            </a>
            
            <!-- Hover Menu -->
            <div class="cyber-user-menu">
                <div class="cyber-user-name mb-2 text-nowrap">
                    <span class="text-info">></span> {{ \Alxarafe\Infrastructure\Auth\Auth::$user->name ?? \Alxarafe\Infrastructure\Auth\Auth::$user->username }}
                </div>
                <a href="index.php?module=Admin&controller=Profile" class="cyber-menu-link d-block mb-2">
                    <i class="fas fa-id-card me-2"></i> {{ \Alxarafe\Infrastructure\Lib\Trans::_('my_profile') }}
                </a>
                <a href="index.php?module=Admin&controller=Auth&action=logout" class="cyber-menu-link d-block text-danger">
                    <i class="fas fa-power-off me-2"></i> {{ \Alxarafe\Infrastructure\Lib\Trans::_('logout') }}
                </a>
            </div>
        </div>
    @else
        <!-- Guest Access icon -->
        @if(stripos($_SERVER['QUERY_STRING'] ?? '', 'controller=Auth') === false)
            @include('component.menu_item', ['url' => 'index.php?module=Admin&controller=Auth', 'icon' => 'fas fa-sign-in-alt', 'title' => \Alxarafe\Infrastructure\Lib\Trans::_('login_button')])
        @endif
    @endif

    <!-- Notifications (Admin feature) -->
    @if(\Alxarafe\Infrastructure\Auth\Auth::isLogged())
        @php
            $notifications = \Modules\Admin\Service\NotificationManager::getUnread();
            $unreadCount = $notifications->count();
        @endphp
        @include('component.menu_item', ['url' => '#', 'icon' => 'fas fa-bell', 'title' => \Alxarafe\Infrastructure\Lib\Trans::_('notifications'), 'colorClass' => $unreadCount > 0 ? 'text-danger animate-pulse' : 'text-secondary', 'badge' => $unreadCount > 0 ? $unreadCount : null])
    @endif

    <!-- User Menu Items (Runtime) -->
    @if(!empty($user_menu) && is_array($user_menu))
        @foreach($user_menu as $item)
            @php
                $url = $item['url'] ?? '#';
                $labelKey = $item['label_key'] ?? '';
                $label = $item['label'] ?? $labelKey;
                
                $isSetDefault = (strpos($url, 'action=setDefault') !== false);
                $isProfile = !$isSetDefault && ($labelKey === 'Profile' || $labelKey === 'My Profile' || (strpos($url, 'Profile') !== false && strpos($url, 'action=') === false));
                $isLogout = ($labelKey === 'Logout' || $labelKey === 'logout' || strpos($url, 'logout') !== false);
            @endphp
            @continue($isProfile || $isLogout)
            
            @include('component.menu_item', ['url' => $url, 'icon' => $item['icon'] ?? 'fas fa-circle', 'label' => $label])
        @endforeach
    @endif

    <!-- Spacer -->
    <div class="mt-auto"></div>

    <!-- Language Switcher -->
    <div class="mb-4 position-relative cyber-icon-container">
        @include('partial.lang_switcher', ['class' => 'dropstart'])
    </div>

    <!-- Theme Switcher (Unified via shared partial) -->
    <div class="mb-4 position-relative cyber-icon-container">
        @include('partial.theme_switcher', ['class' => 'dropstart'])
    </div>


</div>

<style>
    /* Ensure the Bootstrap dropdown doesn't look totally off in Cyberpunk */
    .cyber-rightbar .dropdown-menu {
        background-color: rgba(10, 20, 30, 0.98);
        border: 1px solid #00f0ff;
        color: #fff;
    }
    .cyber-rightbar .dropdown-item {
        color: #888;
    }
    .cyber-rightbar .dropdown-item:hover {
        background: linear-gradient(90deg, rgba(0, 240, 255, 0.1), transparent);
        color: #00f0ff;
    }
    .cyber-rightbar .dropdown-header {
        color: #00f0ff;
        font-family: 'Courier New', monospace;
        font-size: 0.7em;
        text-transform: uppercase;
    }

    /* Ensure all icons in the right bar are consistently sized */
    .cyber-rightbar .cyber-retro-icon,
    .cyber-rightbar .menu-item-icon {
        font-size: 2rem !important;
        filter: drop-shadow(0 0 3px currentColor);
    }

    /* Consistent spacing between right-bar items */
    .cyber-rightbar .menu-item-container,
    .cyber-rightbar .cyber-retro-container {
        margin-bottom: 1.5rem;
        text-align: center;
    }

    /* Original Cyberpunk Styles */
    .cyber-rightbar {
        position: fixed;
        top: 0;
        right: 0;
        height: 100vh;
        width: 80px;
        background-color: rgba(5, 10, 15, 0.95);
        border-left: 1px solid #00f0ff; 
        z-index: 1040; 
        box-shadow: -5px 0 15px rgba(0, 240, 255, 0.1);
        padding-top: 1rem;
        transition: all 0.3s ease;
    }
    ...
</style>

<style>
    /* Injected Styles for Cyberpunk Right Bar */
    .cyber-rightbar {
        position: fixed;
        top: 0;
        right: 0;
        height: 100vh;
        width: 80px;
        background-color: rgba(5, 10, 15, 0.95);
        border-left: 1px solid #00f0ff; 
        z-index: 1040; 
        box-shadow: -5px 0 15px rgba(0, 240, 255, 0.1);
        padding-top: 1rem;
        transition: all 0.3s ease;
    }

    .cyber-icon {
        color: #66fcf1; 
        transition: all 0.3s ease;
        text-shadow: 0 0 5px rgba(102, 252, 241, 0.5);
    }
    
    .cyber-icon:hover {
        color: #fff;
        text-shadow: 0 0 10px #00f0ff, 0 0 20px #00f0ff;
        transform: scale(1.1);
    }

    .cyber-user-menu {
        position: absolute;
        right: 60px; /* Overlap with the bar to prevent gap */
        top: 0;
        background: rgba(10, 20, 30, 0.98);
        border: 1px solid #00f0ff;
        border-right: none; /* Merge visully with bar */
        padding: 15px;
        padding-right: 25px; /* Extra padding to push content left */
        
        /* Transition Logic for "Grace Period" */
        visibility: hidden;
        opacity: 0;
        transition: visibility 0s 0.3s, opacity 0.3s ease-in; /* Wait 0.3s before hiding */
        
        width: 240px;
        text-align: left;
        box-shadow: -5px 5px 15px rgba(0, 0, 0, 0.8);
        z-index: 1050;
        clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 10px 50%); /* Cyber shape */
    }
    
    /* Invisible bridge to catch mouse */
    .cyber-user-menu::after {
        content: '';
        position: absolute;
        right: -20px;
        top: 0;
        width: 40px; /* Wider bridge */
        height: 100%;
        background: transparent;
    }
    
    .group-hover-glow:hover .cyber-user-menu {
        visibility: visible;
        opacity: 1;
        transition-delay: 0s; /* Show immediately */
        animation: none; 
    }

    .cyber-user-name {
        font-family: 'Courier New', monospace;
        color: #fff;
        font-weight: bold;
        border-bottom: 1px solid #333;
        padding-bottom: 5px;
    }

    .cyber-menu-link {
        color: #888;
        transition: 0.2s;
        text-decoration: none;
        padding: 5px 0;
        display: block;
    }
    .cyber-menu-link:hover {
        color: #00f0ff;
        padding-left: 10px;
        background: linear-gradient(90deg, rgba(0, 240, 255, 0.1), transparent);
    }
    .cyber-menu-link.text-danger:hover {
        color: #ff003c;
        background: linear-gradient(90deg, rgba(255, 0, 60, 0.1), transparent);
    }

    .cyber-icon.text-danger { color: #ff003c !important; text-shadow: 0 0 5px rgba(255, 0, 60, 0.5); }
    .animate-pulse { animation: cyber-pulse 2s infinite; }
    @keyframes cyber-pulse {
        0% { text-shadow: 0 0 5px rgba(255, 0, 60, 0.5); opacity: 1; }
        50% { text-shadow: 0 0 20px rgba(255, 0, 60, 0.8); opacity: 0.8; }
        100% { text-shadow: 0 0 5px rgba(255, 0, 60, 0.5); opacity: 1; }
    }
    
    .cyber-scanline-v {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(to bottom, transparent 50%, rgba(0, 240, 255, 0.02) 50%);
        background-size: 100% 4px;
        pointer-events: none;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 767.98px) {
        .cyber-rightbar {
            top: auto;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 60px;
            flex-direction: row !important;
            padding-top: 0;
            padding-left: 0;
            justify-content: space-around;
            border-left: none;
            border-top: 1px solid #00f0ff;
            box-shadow: 0 -5px 15px rgba(0, 240, 255, 0.1);
        }
        
        .cyber-rightbar .mt-auto { display: none; } /* Remove spacer */
        .cyber-rightbar .cyber-scanline-v { background: linear-gradient(to right, transparent 50%, rgba(0, 240, 255, 0.02) 50%); background-size: 4px 100%; }

        .cyber-icon-container {
            margin-bottom: 0 !important;
            display: flex;
            align-items: center;
        }

        .cyber-user-menu {
            bottom: 70px;
            top: auto;
            left: 0;
            right: 0;
            width: 100%;
            border-right: 1px solid #00f0ff;
            clip-path: none;
        }
        
        .group-hover-glow {
            margin-bottom: 0 !important;
            margin-top: 0 !important;
        }
    }
</style>

