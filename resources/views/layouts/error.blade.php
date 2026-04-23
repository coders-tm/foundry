<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="keywords" content="Fitness, Wellbeing Centre" />
    <meta name="description" content="Fitness & Wellbeing Centre | Farsley, Leeds, LS28 5LY" />
    <meta name="author" content="{{ config('app.url') }}" />
    <meta name="viewport" content=" width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        {{ $title }} | {{ config('app.name') }}
    </title>
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.foundry.com">
    <link rel="stylesheet" type="text/css" href="https://cdn.foundry.com/fontawesome/css/all.min.css" />
    <link rel="stylesheet" type="text/css" href="{{ asset('statics/css/styles.min.css') }}" />

    {{-- App Style --}}
    <link rel="stylesheet" type="text/css" href="{{ theme('css/app.css', 'foundation') }}" />

    @yield('style')
</head>

<body>
    [header layout="classic"]

    <div style="padding: 30px 0px">
        @yield('message')
    </div>

    [footer]
</body>

</html>
