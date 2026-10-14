<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Server-Rendered HTML Template Engine.
 * - Enforces XSS output encoding via `View::e()`.
 * - Appends automatic `?v=filemtime()` to CSS/JS assets via `View::asset()`
 *   so browsers and Telegram Mini App WebViews never serve stale styles or scripts.
 */
final class View
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function csrfField(): string
    {
        return Csrf::field();
    }

    public static function asset(string $relativePath): string
    {
        $clean = ltrim($relativePath, '/');
        $fullPath = (defined('BULBULA_PUBLIC') ? BULBULA_PUBLIC : dirname(__DIR__, 2) . '/public_html') . '/assets/' . $clean;
        clearstatcache(true, $fullPath);
        $mtime = is_file($fullPath) ? (int) @filemtime($fullPath) : time();
        return '/assets/' . $clean . '?v=' . $mtime;
    }

    public static function categoryImage(string $slug): string
    {
        $map = [
            'health-medical'          => 'img/cards/photo-cat-health.jpg',
            'clinics-medical-centers' => 'img/cards/photo-biz-clinic.jpg',
            'pharmacies'              => 'img/cards/photo-biz-pharmacy.jpg',
            'dental-clinics'          => 'img/cards/photo-biz-dental.jpg',
            'food-coffee'             => 'img/cards/photo-cat-coffee.jpg',
            'cafes-coffee-roasters'   => 'img/cards/photo-biz-coffee.jpg',
            'restaurants-bakeries'    => 'img/cards/photo-cat-coffee.jpg',
            'groceries-retail'        => 'img/cards/photo-cat-supermarket.jpg',
            'supermarkets'            => 'img/cards/photo-biz-supermarket.jpg',
            'education-childcare'     => 'img/cards/photo-cat-education.jpg',
            'schools-kindergartens'   => 'img/cards/photo-cat-education.jpg',
            'home-auto-services'      => 'img/cards/photo-cat-auto.jpg',
            'auto-repair-garages'     => 'img/cards/photo-biz-garage.jpg',
            'salons-barbershops'      => 'img/cards/photo-cat-auto.jpg',
            'finance-professional'    => 'img/cards/photo-cat-finance.jpg',
            'banks-atms'              => 'img/cards/photo-cat-finance.jpg',
        ];
        return self::asset($map[$slug] ?? 'img/cards/photo-cat-health.jpg');
    }

    public static function areaImage(string $slug): string
    {
        $map = [
            'bulbula-93-mazoria'     => 'img/cards/photo-area-93-mazoria.jpg',
            'bulbula-mariam-mazoria' => 'img/cards/photo-area-mariam.jpg',
            'bulbula-condominiums'   => 'img/cards/photo-cat-supermarket.jpg',
            'bulbula-gorge-airport'  => 'img/cards/photo-cat-finance.jpg',
        ];
        return self::asset($map[$slug] ?? 'img/cards/photo-area-93-mazoria.jpg');
    }

    public static function branchImage(array $b): string
    {
        if (!empty($b['cover_image_url'])) {
            return (string) $b['cover_image_url'];
        }
        $catSlug    = (string) ($b['primary_category_slug'] ?? '');
        $bizSlug    = (string) ($b['business_slug'] ?? '');
        $branchSlug = (string) ($b['branch_slug'] ?? '');

        if (str_contains($branchSlug, 'express')) {
            return self::asset('img/cards/photo-biz-express.jpg');
        }
        if (str_contains($bizSlug, 'pharmacy') || $catSlug === 'pharmacies') {
            return self::asset('img/cards/photo-biz-pharmacy.jpg');
        }
        if (str_contains($bizSlug, 'dental') || $catSlug === 'dental-clinics') {
            return self::asset('img/cards/photo-biz-dental.jpg');
        }
        if (str_contains($bizSlug, 'coffee') || str_contains($catSlug, 'coffee') || str_contains($catSlug, 'food')) {
            return self::asset('img/cards/photo-biz-coffee.jpg');
        }
        if (str_contains($bizSlug, 'supermarket') || str_contains($catSlug, 'supermarket') || str_contains($catSlug, 'grocer')) {
            return self::asset('img/cards/photo-biz-supermarket.jpg');
        }
        if (str_contains($bizSlug, 'garage') || str_contains($bizSlug, 'auto') || str_contains($catSlug, 'auto')) {
            return self::asset('img/cards/photo-biz-garage.jpg');
        }
        if (str_contains($bizSlug, 'clinic') || str_contains($catSlug, 'clinic') || str_contains($catSlug, 'health')) {
            return self::asset('img/cards/photo-biz-clinic.jpg');
        }
        return self::categoryImage($catSlug);
    }

    public static function render(string $template, array $data = [], ?string $layout = 'public'): string
    {
        $viewsRoot = (defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2)) . '/src/Views';
        $templateFile = $viewsRoot . '/' . ltrim($template, '/') . '.php';

        if (!is_file($templateFile)) {
            return self::renderStandaloneError(500, 'Missing View Template', $template);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutFile = $viewsRoot . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            return $content;
        }

        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }

    public static function component(string $componentName, array $props = []): string
    {
        $viewsRoot = (defined('BULBULA_ROOT') ? BULBULA_ROOT : dirname(__DIR__, 2)) . '/src/Views/components';
        $file = $viewsRoot . '/' . ltrim($componentName, '/') . '.php';

        if (!is_file($file)) {
            return '';
        }

        extract($props, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    public static function renderStandaloneError(int $code, string $title, string $message): string
    {
        $safeTitle = self::e($title);
        $safeMsg   = self::e($message);
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$code} — {$safeTitle} | Bulbula.et</title>
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/base.css">
</head>
<body class="b-error-page">
    <main class="b-container" style="padding: 4rem 1.25rem; max-width: 36rem; margin: 0 auto;">
        <span class="b-badge b-badge--orange">HTTP {$code}</span>
        <h1 style="margin-top: 0.75rem;">{$safeTitle}</h1>
        <p style="margin-top: 0.5rem; color: var(--color-text-secondary);">{$safeMsg}</p>
        <div style="margin-top: 1.5rem;">
            <a href="/" class="b-btn b-btn--primary">Return to Bulbula.et Directory</a>
        </div>
    </main>
</body>
</html>
HTML;
    }
}
