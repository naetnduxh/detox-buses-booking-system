<?php
require_once __DIR__ . '/../config/db.php';

session_unset();
session_destroy();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

set_flash('info', 'You have been safely logged out.');
header('Location: /detox_buses/index.php');
exit;
