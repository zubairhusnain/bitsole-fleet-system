<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Omayer Fleet System') }}</title>

    <!-- CSRF for SPA requests -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
      window.AppConfig = {
        timezone: "{{ config('app.timezone') }}",
      };
    </script>

    <!-- Head styles aligned with AdminLTE samples -->
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="icon" type="image/png" href="{{ asset('images/login-page-logo.png') }}" />

    <!-- Vite-bundled styles and SPA entry -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
      @vite([
        'resources/css/app.css',
        'resources/js/app.js',
      ])
    @endif

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAnlsNp3vHTiOvcAOwfQQzAm2omvbnh-REDACTED&libraries=places,drawing&loading=async&language=en" async defer></script>
  </head>
  <body class="layout-fixed sidebar-expand-lg sidebar-mini sidebar-open bg-body-tertiary">
    <div id="app"></div>

    <!-- Footer scripts aligned with AdminLTE samples -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>

    <!-- AdminLTE JS is loaded by SPA after route/body classes are set -->

    <!-- Optional: OverlayScrollbars init for AdminLTE sidebar if present -->
    <script>
      const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
      document.addEventListener('DOMContentLoaded', function () {
        const wrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
        if (wrapper && window.OverlayScrollbarsGlobal?.OverlayScrollbars) {
          window.OverlayScrollbarsGlobal.OverlayScrollbars(wrapper, {
            scrollbars: { theme: 'os-theme-light', autoHide: 'leave', clickScroll: true },
          });
        }
      });
    </script>
  </body>
</html>
