<?php
/**
 * Web App Manifest (served as application/manifest+json).
 * Reached at /manifest.webmanifest via an .htaccess rewrite.
 */
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/manifest+json; charset=utf-8');

$name = get_setting('site_name', SITE_NAME);
echo json_encode([
    'name'             => $name,
    'short_name'       => 'PWF',
    'description'      => get_setting('site_description', SITE_TAGLINE),
    'start_url'        => BASE_URI . '/',
    'scope'           => BASE_URI . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#0b1120',
    'theme_color'      => '#063566',
    'icons'            => [
        // Organisation logo used as the PWA icon (any + maskable).
        ['src' => ASSET_URI . '/images/logo-256.webp', 'sizes' => '512x512', 'type' => 'image/jpeg', 'purpose' => 'any maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
