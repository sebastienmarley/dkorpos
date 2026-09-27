<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<script>
    if (localStorage.getItem('flux-sidebar-collapsed-desktop') === null && window.innerWidth < 1280) {
        localStorage.setItem('flux-sidebar-collapsed-desktop', 'true');
    }
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
