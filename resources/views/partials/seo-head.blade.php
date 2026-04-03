@php
    $defaultSeo = isset($defaultSeo) && is_array($defaultSeo) ? $defaultSeo : [];
    $routeSeo = isset($routeSeo) && is_array($routeSeo) ? $routeSeo : [];
    $seo = isset($seo) && is_array($seo) ? $seo : [];

    $toAbsoluteUrl = function ($value): ?string {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (\Illuminate\Support\Str::startsWith($value, '//')) {
            return request()->getScheme() . ':' . $value;
        }

        return url($value);
    };

    $normalizeSchemaMedia = function ($value, ?string $key = null) use (&$normalizeSchemaMedia, $toAbsoluteUrl) {
        if (is_array($value)) {
            if (in_array($key, ['image', 'logo', 'thumbnailUrl'], true)) {
                return array_values(array_filter(array_map(function ($item) use ($normalizeSchemaMedia, $toAbsoluteUrl) {
                    if (is_string($item)) {
                        return $toAbsoluteUrl($item);
                    }

                    return $normalizeSchemaMedia($item);
                }, $value)));
            }

            foreach ($value as $childKey => $childValue) {
                $value[$childKey] = $normalizeSchemaMedia($childValue, is_string($childKey) ? $childKey : null);
            }
        } elseif (is_string($value) && in_array($key, ['image', 'logo', 'thumbnailUrl'], true)) {
            return $toAbsoluteUrl($value);
        }

        return $value;
    };

    $mergedSeo = array_merge($defaultSeo, $routeSeo, $seo);
    $pageTitle = $pageTitle ?? ($mergedSeo['title'] ?? ($siteTitle ?? 'GrinMuzik'));
    $siteMetaTitle = $siteTitle ?? 'GrinMuzik';

    if ($pageTitle !== $siteMetaTitle && ! str_contains($pageTitle, $siteMetaTitle)) {
        $pageTitle .= ' | ' . $siteMetaTitle;
    }

    $metaDescription = $mergedSeo['description'] ?? 'Discover independent music, playlists, charts, and artists on GrinMuzik.';
    $canonicalUrl = $mergedSeo['canonical'] ?? url()->current();
    $robots = $mergedSeo['robots'] ?? 'index,follow';
    $ogType = $mergedSeo['type'] ?? 'website';
    $ogTitle = $mergedSeo['og_title'] ?? $pageTitle;
    $ogDescription = $mergedSeo['og_description'] ?? $metaDescription;
    $ogUrl = $mergedSeo['og_url'] ?? $canonicalUrl;
    $ogImage = null;

    foreach ([
        $seo['image'] ?? null,
        $routeSeo['image'] ?? null,
        $defaultSeo['image'] ?? null,
    ] as $candidateImage) {
        $resolvedImage = $toAbsoluteUrl($candidateImage);

        if ($resolvedImage) {
            $ogImage = $resolvedImage;
            break;
        }
    }

    $twitterCard = $mergedSeo['twitter_card'] ?? ($ogImage ? 'summary_large_image' : 'summary');
    $siteNameTag = trim((string) ($mergedSeo['site_name'] ?? ($siteName ?? 'GrinMuzik')));
    $siteNameTag = $siteNameTag !== '' ? $siteNameTag : 'GrinMuzik';
    $ogImageType = $mergedSeo['image_type'] ?? null;
    $ogImageWidth = $mergedSeo['image_width'] ?? null;
    $ogImageHeight = $mergedSeo['image_height'] ?? null;
    $ogImageAlt = $mergedSeo['image_alt'] ?? null;

    $jsonLd = $mergedSeo['json_ld'] ?? [];

    if ($jsonLd && array_key_exists('@context', $jsonLd)) {
        $jsonLd = [$jsonLd];
    }

    $schemaBlocks = array_values(array_filter(array_merge(
        [$organizationSchema ?? null, $websiteSchema ?? null],
        is_array($jsonLd) ? $jsonLd : []
    )));
    $schemaBlocks = array_map(fn ($schema) => $normalizeSchemaMedia($schema), $schemaBlocks);
@endphp

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta name="theme-color" content="{{ $themePrimaryColor ?? '#305CDE' }}">
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="application-name" content="{{ $siteNameTag }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ $siteNameTag }}">
<meta name="apple-mobile-web-app-status-bar-style" content="default">

<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:url" content="{{ $ogUrl }}">
<meta property="og:site_name" content="{{ $siteNameTag }}">
@if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    @if($ogImageType)
        <meta property="og:image:type" content="{{ $ogImageType }}">
    @endif
    @if($ogImageWidth)
        <meta property="og:image:width" content="{{ $ogImageWidth }}">
    @endif
    @if($ogImageHeight)
        <meta property="og:image:height" content="{{ $ogImageHeight }}">
    @endif
    @if($ogImageAlt)
        <meta property="og:image:alt" content="{{ $ogImageAlt }}">
    @endif
@endif

<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
@if($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
    @if($ogImageAlt)
        <meta name="twitter:image:alt" content="{{ $ogImageAlt }}">
    @endif
@endif

@foreach($schemaBlocks as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
@endforeach
