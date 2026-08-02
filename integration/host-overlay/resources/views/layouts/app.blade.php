<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- PWA Meta Tags -->
    <meta name="application-name" content="Unified WorkCore">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Unified WorkCore">
    <meta name="description" content="Real-time chat application for seamless communication">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#724FF9">

    <!-- Icons -->
    <link rel="apple-touch-icon" sizes="180x180"
        href="{{ getImageUrl(setting('favicon', asset('apple-touch-icon.png'))) }}">
    <link rel="icon" type="image/png" sizes="32x32"
        href="{{ getImageUrl(setting('favicon', asset('favicon-16x16.png'))) }}">
    <link rel="icon" type="image/png" sizes="16x16"
        href="{{ getImageUrl(setting('favicon', asset('favicon-32x32.png'))) }}">
    <link rel="shortcut icon" href="{{ getImageUrl(setting('favicon', asset('favicon.ico'))) }}">

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">

    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileColor" content="#724FF9">
    <meta name="msapplication-TileImage" content="/mstile-150x150.png">
    <title>{{ setting('app_name', config('app.name')) }} - @yield('title', 'Chat Application')</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#724FF9',
                        secondary: '#724FF9',
                        teal: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#724FF9', // Secondary
                            600: '#724FF9', // Primary
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                            950: '#052e16',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">


    @stack('styles')
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 font-['Inter']">

    <div class="flex min-h-screen">

        @if (config('app.admin_email') === auth()->user()->email)
            <!-- Mini Sidebar -->
            @include('layouts.partials.mini-sidebar')
        @endif

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            @yield('content')
        </div>

    </div>

    <!-- Lucide Icons -->
    <script src="{{ asset('js/lucide.min.js') }}"></script>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // User dropdown toggle
        const userMenuBtn = document.getElementById('user-menu-btn');
        if (userMenuBtn) {
            userMenuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = document.getElementById('user-dropdown');
                dropdown.classList.toggle('show');
            });

            // Reinitialize icons after dropdown opens
            userMenuBtn.addEventListener('click', function() {
                setTimeout(() => lucide.createIcons(), 10);
            });
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('user-dropdown');
            if (dropdown && !e.target.closest('#user-menu-btn') && !e.target.closest('#user-dropdown')) {
                dropdown.classList.remove('show');
            }
        });
    </script>

    @stack('scripts')
</body>

</html>
