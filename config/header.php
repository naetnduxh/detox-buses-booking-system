<?php
require_once __DIR__ . '/db.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' | Detox Buses' : 'Detox Buses - Online Booking & Self-Service Cash-Out'; ?></title>
    <link rel="stylesheet" href="/detox_buses/assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220%22><text y=%2226%22 font-size=%2226%22>🚌</text></svg>">
</head>
<body>

<header class="navbar">
    <div class="nav-container">
        <a href="/detox_buses/index.php" class="brand-logo">
            <span class="logo-icon">🚌</span>
            <span>Detox Buses</span>
        </a>

        <ul class="nav-links">
            <li><a href="/detox_buses/index.php">Find Trips</a></li>

            <?php if (is_logged_in()): ?>
                <?php if (is_admin()): ?>
                    <li><a href="/detox_buses/admin/dashboard.php" class="user-badge admin-badge">🛡️ Admin Dashboard</a></li>
                    <li><a href="/detox_buses/admin/manage_buses.php">Fleet</a></li>
                    <li><a href="/detox_buses/admin/manage_routes.php">Schedules</a></li>
                    <li><a href="/detox_buses/admin/view_refunds.php">Refund Audit</a></li>
                <?php else: ?>
                    <li><a href="/detox_buses/passenger/my_bookings.php">My Bookings & Cash-Out</a></li>
                <?php endif; ?>

                <li>
                    <span class="user-badge">
                        👤 <?= htmlspecialchars($user['name']); ?>
                    </span>
                </li>
                <li><a href="/detox_buses/auth/logout.php" class="nav-btn nav-btn-outline">Log Out</a></li>
            <?php else: ?>
                <li><a href="/detox_buses/auth/login.php" class="nav-btn nav-btn-outline">Sign In</a></li>
                <li><a href="/detox_buses/auth/register.php" class="nav-btn nav-btn-primary">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>

<main class="main-content">
    <?php display_flash(); ?>
