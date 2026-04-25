<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="keywords" content="{{ $meta_keywords ?? '' }}" />
    <meta name="description" content="{{ $meta_description ?? '' }}" />
    <meta name="author" content="{{ $url ?? config('app.url') }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>
        {{ $meta_title ?? $title . ' | ' . config('app.name') }}
    </title>

    @if (config('app.env') == 'local')
        <meta name="robots" content="noindex">
        <meta name="googlebot" content="noindex">
    @endif

    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('statics/animate.min.css') }}">
    <link rel="stylesheet" href="{{ asset('statics/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ theme('css/app.css', 'foundation') }}">

    {{-- Theme Settings --}}
    <style type="text/css">
        :root {
            --color-primary: {{ $theme->get('colors.primary', '#10b981') }};
            --color-primary-hover: {{ $theme->get('colors.primary_hover', '#059669') }};
            --color-secondary: {{ $theme->get('colors.secondary', '#f59e0b') }};
            --color-tertiary: {{ $theme->get('colors.tertiary', '#6366f1') }};
            --color-background: {{ $theme->get('colors.background', '#0f0f0f') }};
            --color-surface: {{ $theme->get('colors.surface', '#0a0a0a') }};
            --color-card: {{ $theme->get('colors.card', '#1a1a1a') }};
            --color-warm: {{ $theme->get('colors.warm', '#271c1c') }};
            --color-border: {{ $theme->get('colors.border', '#2a2a2a') }};
            --color-body: {{ $theme->get('colors.body', '#ffffff') }};
            --color-muted: {{ $theme->get('colors.muted', '#a0a0a0') }};
            --font-display: {{ $theme->get('fonts.display', 'Inter, sans-serif') }};
            --font-body: {{ $theme->get('fonts.body', 'Inter, sans-serif') }};
            --radius: {{ $theme->get('radius.base', '0.25rem') }};
            --radius-lg: {{ $theme->get('radius.lg', '0.5rem') }};
            --radius-xl: {{ $theme->get('radius.xl', '0.75rem') }};
            --radius-full: {{ $theme->get('radius.full', '9999px') }};
        }
    </style>

    {{-- JS Libraries --}}
    <script src="{{ asset('statics/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('statics/js/jquery-migrate-3.3.2.min.js') }}"></script>
    <script src="{{ asset('statics/js/jquery.easing.js') }}"></script>
    <script src="{{ asset('statics/js/jquery-waypoints.js') }}"></script>
    <script src="{{ asset('statics/js/jquery-validate.js') }}"></script>
    <script src="{{ asset('statics/js/tether.min.js') }}"></script>
    <script src="{{ asset('statics/js/jquery.prettyPhoto.js') }}"></script>
    <script src="{{ asset('statics/js/numinate.min.js') }}"></script>
    <script src="{{ asset('statics/js/imagesloaded.min.js') }}"></script>
    <script src="{{ asset('statics/js/slick.min.js') }}"></script>
    <script src="{{ asset('statics/js/jquery-isotope.js') }}"></script>
    <script src="{{ asset('statics/js/fullcalendar/main.js') }}"></script>
    <script src="{{ theme('js/app.js', 'foundation') }}"></script>

    {{-- Google Analytics --}}
    @if (config('services.gtag'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.gtag') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', new Date());
            gtag('config', '{{ config('services.gtag') }}');
        </script>
    @endif

    @stack('content_for_head')
</head>

<body class="bg-background text-body overflow-x-hidden font-body antialiased">

    @sections('header')

    @yield('content')

    @sections('footer')

    {{-- Scroll to top --}}
    <a id="totop" href="#top"
        class="fixed bottom-6 right-6 z-50 w-10 h-10 flex items-center justify-center bg-primary text-body rounded-full shadow-lg opacity-0 invisible transition-all duration-300 hover:bg-primary-hover">
        <i class="fa fa-angle-up"></i>
    </a>
</body>

</html>
