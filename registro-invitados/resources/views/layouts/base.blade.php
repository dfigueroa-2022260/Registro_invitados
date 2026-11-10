<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>@yield('titulo', 'UDEVIPO · Registro de invitados')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="base-url" content="{{ parse_url(url('/'), PHP_URL_PATH) }}" />
    <meta name="assets-url" content="{{ parse_url(asset('assets'), PHP_URL_PATH) }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/base.css') }}" />
    @stack('estilos')
  </head>
  <body>
    @yield('contenido')
    @stack('scripts')
  </body>
</html>
