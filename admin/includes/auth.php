<?php
/**
 * Admin bootstrap + guard. Every admin page (except login) includes this FIRST:
 *   require __DIR__ . '/includes/auth.php';
 * It loads the app engine and redirects to the login page if the visitor isn't signed in.
 * Pass a permission to also gate the page: require_admin('campaigns.manage').
 */
require_once __DIR__ . '/../../includes/config.php';
require_admin();
