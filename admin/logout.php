<?php
/**
 * Admin logout — destroys the session and returns to the login screen.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

logout();
pwf_session_start();               // fresh session so the flash can be stored
set_flash('success', 'You have been signed out.');
redirect('/admin/login');
