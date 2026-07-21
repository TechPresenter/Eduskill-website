<?php
require __DIR__ . '/includes/auth.php';
$__cfg = resource_config('testimonials');
require_admin($__cfg['permission']);
$admin_title = (isset($_GET['id']) ? 'Edit ' : 'New ') . strtolower($__cfg['singular']);
require __DIR__ . '/includes/header.php';
admin_resource_form('testimonials');
require __DIR__ . '/includes/footer.php';
