<?php
defined('ESK') || exit('No direct access.');
/**
 * Public site header. A page sets $page_title / $meta_description before including this, then
 * includes footer.php at the end. Loads the compiled static Tailwind CSS + runtime brand palette,
 * plus AOS and Font Awesome from CDN (no build step, no npm).
 */
$siteName = (string) setting('site_name', APP_NAME);
$metaTitle = isset($page_title) && $page_title !== '' ? $page_title . ' · ' . $siteName : $siteName;
$metaDesc = $meta_description ?? (string) setting('site_tagline', 'Empowering communities through education and skills.');
?><!doctype html>
<html lang="en" class="h-full scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($metaTitle) ?></title>
  <meta name="description" content="<?= e($metaDesc) ?>">
  <meta property="og:title" content="<?= e($metaTitle) ?>">
  <meta property="og:description" content="<?= e($metaDesc) ?>">
  <meta property="og:type" content="website">
  <link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>">
  <script>
    (function () { try { var t = localStorage.getItem('esk-theme');
      if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
    } catch (e) {} })();
  </script>
  <?= palette_style() ?>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="min-h-full">
  <?php include INCLUDES_PATH . '/navbar.php'; ?>
  <main id="content">
