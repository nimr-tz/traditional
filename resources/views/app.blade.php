<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php($seo = $page['props']['seo'] ?? null)

        <title inertia>{{ $seo['title'] ?? config('app.name', 'Laravel') }}</title>

        @if ($seo)
            @if ($seo['description'])
                <meta inertia="description" name="description" content="{{ $seo['description'] }}">
            @endif
            <meta inertia="robots" name="robots" content="index, follow">
            <link inertia="canonical" rel="canonical" href="{{ $seo['canonicalUrl'] }}">
            <link inertia="sitemap" rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">
            <meta inertia="og:type" property="og:type" content="website">
            <meta inertia="og:site_name" property="og:site_name" content="TMSC">
            <meta inertia="og:title" property="og:title" content="{{ $seo['title'] }}">
            @if ($seo['description'])
                <meta inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
            @endif
            <meta inertia="og:url" property="og:url" content="{{ $seo['canonicalUrl'] }}">
            <meta inertia="og:image" property="og:image" content="{{ $seo['imageUrl'] }}">
            <meta inertia="og:image:alt" property="og:image:alt" content="Traditional medicine herbs and mortar">
            <meta inertia="twitter:card" name="twitter:card" content="summary_large_image">
            <meta inertia="twitter:title" name="twitter:title" content="{{ $seo['title'] }}">
            @if ($seo['description'])
                <meta inertia="twitter:description" name="twitter:description" content="{{ $seo['description'] }}">
            @endif
            <meta inertia="twitter:image" name="twitter:image" content="{{ $seo['imageUrl'] }}">
            <script inertia="event-json-ld" type="application/ld+json">{!! json_encode($seo['structuredData'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=work-sans:400,500,600,700|lora:500,600,700" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
