<?php
require_once __DIR__ . '/../includes/config.php';
logout_user();
redirect('admin/login.php');
