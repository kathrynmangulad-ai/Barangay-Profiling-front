<?php
/* Front door. Sends visitors to their dashboard if signed in, else to login. */
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    // role_home() already returns a full URL.
    redirect(role_home($_SESSION['role'] ?? ''));
}
redirect(url('auth/login.php'));
