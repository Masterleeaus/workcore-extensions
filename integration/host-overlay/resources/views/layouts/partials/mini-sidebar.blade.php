<div class="mini-sidebar bg-white border-r border-gray-200 flex flex-col items-center py-4">
    <!-- Logo -->
    <div class="mb-8">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center">
            <img src="{{ getImageUrl(setting('logo')) }}" alt="Logo">
        </div>
    </div>

    <!-- Navigation Items -->
    <nav class="flex-1 flex flex-col space-y-2 w-full px-2">
        <a href="{{ route('dashboard') }}" class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i data-lucide="layout-dashboard" class="w-6 h-6"></i>
            <span class="text-xs mt-1">Dashboard</span>
            <span class="tooltip">Dashboard</span>
        </a>

        <a href="{{ route('chat') }}" class="sidebar-item {{ request()->routeIs('chat') ? 'active' : '' }}">
            <i data-lucide="message-square" class="w-6 h-6"></i>
            <span class="text-xs mt-1">Chat</span>
            <span class="tooltip">Chat</span>
        </a>

        <a href="{{ route('users.index') }}" class="sidebar-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i data-lucide="users" class="w-6 h-6"></i>
            <span class="text-xs mt-1">Users</span>
            <span class="tooltip">User Management</span>
        </a>
        <a href="{{ route('settings.index') }}"
            class="sidebar-item {{ request()->routeIs('settings.index') ? 'active' : '' }}">
            <i data-lucide="cog" class="w-6 h-6"></i>
            <span class="text-xs mt-1">Settings</span>
            <span class="tooltip">System Setting</span>
        </a>
    </nav>

    <!-- User Avatar with Dropdown -->
    <div class="relative mt-auto">
        <button id="user-menu-btn" class="sidebar-item">
            <img src="{{ avatar(auth()->user()->name, auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}"
                class="w-10 h-10 rounded-full ring-2 ring-gray-200">
        </button>

        <!-- Dropdown Menu -->
        <div id="user-dropdown" class="dropdown-menu">
            <div class="px-4 py-3 border-b border-gray-200">
                <p class="text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
            </div>
            <a href="{{ route('settings') }}" class="dropdown-item">
                <i data-lucide="settings" class="w-4 h-4"></i>
                <span>Settings</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item w-full text-left text-red-600">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>
</div>
