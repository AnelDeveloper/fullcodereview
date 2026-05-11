<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}" />
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}" />
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0B0817" id="theme-color-meta">
    <meta name="msapplication-navbutton-color" content="#0B0817" id="msapplication-navbutton-color">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" id="apple-status-bar-style">
    <title>QodeShark</title>
    @vite(['resources/js/main.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
