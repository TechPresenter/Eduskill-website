<?php
/**
 * API v1 root — lists available endpoints (self-documenting).
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('GET');

$base = rtrim(APP_URL, '/') . '/api/v1';
api_ok([
    'name'    => get_setting('site_name', SITE_NAME) . ' API',
    'version' => 'v1',
    'endpoints' => [
        'GET  ' . $base . '/blogs?page=&per_page=&q=&category=',
        'GET  ' . $base . '/blogs?slug=my-post',
        'GET  ' . $base . '/events?type=upcoming|past',
        'GET  ' . $base . '/events?slug=my-event',
        'GET  ' . $base . '/gallery  (albums)  ·  /gallery?album=slug (media)',
        'GET  ' . $base . '/team?leadership=1',
        'GET  ' . $base . '/testimonials',
        'GET  ' . $base . '/programs  ·  /programs?slug=education',
        'POST ' . $base . '/contact   {name,email,phone,subject,message}',
        'POST ' . $base . '/volunteer {name,email,phone,area_of_interest,...}',
        'POST ' . $base . '/donate    {donor_name,email,amount,...}',
    ],
    'auth' => (string) get_setting('api_jwt_enabled', '0') === '1'
        ? 'Bearer JWT required on protected endpoints'
        : 'Public (JWT scaffold available, currently disabled)',
]);
