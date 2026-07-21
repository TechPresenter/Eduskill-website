<?php
require __DIR__ . '/includes/auth.php';
$__cfg = resource_config('events');
require_admin($__cfg['permission']);
$admin_title = $__cfg['plural'];
require __DIR__ . '/includes/header.php';
admin_resource_table('events');
require __DIR__ . '/includes/footer.php';
