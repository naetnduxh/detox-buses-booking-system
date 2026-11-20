<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$page_title = "Admin Dashboard";

// Fetch Dashboard Metrics
$total_buses = $pdo->query("SELECT COUNT(*) FROM buses")->fetchColumn();
$total_routes = $pdo->query("SELECT COUNT(*) FROM routes WHERE status = 'Active'")->fetchColumn();
$active_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Confirmed'")->fetchColumn();
$total_refunds_count = $pdo->query("SELECT COUNT(*) FROM refunds")->fetchColumn();

// Financial metrics
$gross_sales = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM bookings WHERE status = 'Confirmed'")->fetchColumn();
$refunds_paid = $pdo->query("SELECT COALESCE(SUM(refund_amount), 0) FROM refunds")->fetchColumn();
$fees_earned = $pdo->query("SELECT COALESCE(SUM(cancellation_fee), 0) FROM refunds")->fetchColumn();
$net_revenue = $gross_sales + $fees_earned;

// Recent 5 Bookings
$recent_bookings = $pdo->query("SELECT bk.*, u.full_name, u.phone, r.origin, r.destination, r.travel_date, r.departure_time, b.bus_name
                                FROM bookings bk
                                JOIN users u ON bk.user_id = u.user_id
                                JOIN routes r ON bk.route_id = r.route_id
                                JOIN buses b ON r.bus_id = b.bus_id
                                ORDER BY bk.created_at DESC LIMIT 5")->fetchAll();

// Recent 5 Refunds
$recent_refunds = $pdo->query("SELECT rf.*, bk.booking_ref, bk.seat_number, u.full_name, r.destination, r.travel_date
                               FROM refunds rf
                               JOIN bookings bk ON rf.booking_id = bk.booking_id
                               JOIN users u ON bk.user_id = u.user_id
                               JOIN routes r ON bk.route_id = r.route_id
                               ORDER BY rf.refunded_at DESC LIMIT 5")->fetchAll();

require_once __DIR__ . '/../config/header.php';
?>

<div class="card-header">
    <div>
        <h2>Company Admin Dashboard</h2>
        <p style="color:var(--slate); font-size:0.9rem;">
            Overview of Detox Buses fleet operations, ticket sales, live manifests, and cash-out audits.
        </p>
    </div>
    <div style="display:flex; gap:0.5rem;">
        <a href="/detox_buses/admin/manage_routes.php?action=new" class="btn btn-primary btn-sm">+ Schedule New Trip</a>
        <a href="/detox_buses/admin/manage_buses.php?action=new" class="btn btn-secondary btn-sm">+ Add Fleet Bus</a>
    </div>
</div>

<!-- Metrics Overview Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="margin-bottom:0; padding:1.4rem; border-left: 4px solid var(--primary);">
        <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Net Revenue</small>
        <div style="font-size: 1.7rem; font-weight: 800; color: var(--primary); margin: 0.3rem 0;">
            <?= format_kes($net_revenue); ?>
        </div>
        <small style="color: var(--slate);">Gross: <?= format_kes($gross_sales); ?> | Fees: <?= format_kes($fees_earned); ?></small>
    </div>

    <div class="card" style="margin-bottom:0; padding:1.4rem; border-left: 4px solid #16a34a;">
        <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Confirmed Bookings</small>
        <div style="font-size: 1.7rem; font-weight: 800; color: #16a34a; margin: 0.3rem 0;">
            <?= $active_bookings; ?> Seats
        </div>
        <small style="color: var(--slate);"><?= $total_routes; ?> Active Schedules</small>
    </div>

    <div class="card" style="margin-bottom:0; padding:1.4rem; border-left: 4px solid #f59e0b;">
        <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Fleet Size</small>
        <div style="font-size: 1.7rem; font-weight: 800; color: #d97706; margin: 0.3rem 0;">
            <?= $total_buses; ?> Buses
        </div>
        <small style="color: var(--slate);"><a href="/detox_buses/admin/manage_buses.php" style="color:inherit; font-weight:700;">Manage Fleet →</a></small>
    </div>

    <div class="card" style="margin-bottom:0; padding:1.4rem; border-left: 4px solid var(--danger);">
        <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Cash-Out Audits</small>
        <div style="font-size: 1.7rem; font-weight: 800; color: var(--danger); margin: 0.3rem 0;">
            <?= $total_refunds_count; ?> Cashed Out
        </div>
        <small style="color: var(--slate);"><a href="/detox_buses/admin/view_refunds.php" style="color:inherit; font-weight:700;">View Audits (<?= format_kes($refunds_paid); ?>) →</a></small>
    </div>
</div>

<!-- Quick Navigation Links -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <a href="/detox_buses/admin/manage_buses.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 1rem;">
        🚌 Fleet Management (CRUD)
    </a>
    <a href="/detox_buses/admin/manage_routes.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 1rem;">
        📅 Route & Fare Scheduling (CRUD)
    </a>
    <a href="/detox_buses/admin/manifest.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 1rem;">
        📋 Passenger Manifests
    </a>
    <a href="/detox_buses/admin/view_refunds.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 1rem;">
        💰 10% Cancellation & Refund Audit
    </a>
</div>

<!-- Recent Confirmed Bookings -->
<div class="card">
    <div class="card-header">
        <h3>Recent Confirmed Bookings</h3>
        <a href="/detox_buses/admin/manifest.php" style="color:var(--primary); font-weight:700; font-size:0.9rem;">View Manifests →</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Passenger</th>
                    <th>Route & Bus</th>
                    <th>Date & Time</th>
                    <th>Seat</th>
                    <th>Pickup Stage</th>
                    <th>Paid</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_bookings)): ?>
                    <tr><td colspan="8" style="text-align:center; color:var(--slate);">No bookings recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_bookings as $b): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['booking_ref']); ?></strong></td>
                            <td>
                                <?= htmlspecialchars($b['full_name']); ?><br>
                                <small style="color:var(--slate);"><?= htmlspecialchars($b['phone']); ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($b['origin']); ?> ➔ <?= htmlspecialchars($b['destination']); ?><br>
                                <small style="color:var(--slate);"><?= htmlspecialchars($b['bus_name']); ?></small>
                            </td>
                            <td>
                                <?= format_date($b['travel_date']); ?><br>
                                <small style="color:var(--primary); font-weight:700;"><?= format_time($b['departure_time']); ?></small>
                            </td>
                            <td><span class="badge badge-info">Seat #<?= $b['seat_number']; ?></span></td>
                            <td><small style="color:#b45309; font-weight:700;">📍 <?= htmlspecialchars($b['boarding_point']); ?></small></td>
                            <td><strong><?= format_kes($b['amount_paid']); ?></strong></td>
                            <td>
                                <?php if ($b['status'] === 'Confirmed'): ?>
                                    <span class="badge badge-success">Confirmed</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Refunded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Refunds Audit -->
<div class="card">
    <div class="card-header">
        <h3>Recent Self-Service Cash-Outs (10% Fee Rule)</h3>
        <a href="/detox_buses/admin/view_refunds.php" style="color:var(--primary); font-weight:700; font-size:0.9rem;">Full Refund Audit Log →</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Reversal Ref</th>
                    <th>Booking Ref</th>
                    <th>Passenger</th>
                    <th>Original Fare</th>
                    <th>10% Fee Kept</th>
                    <th>90% Refunded</th>
                    <th>Time Audited</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_refunds)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--slate);">No refunds recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_refunds as $rf): ?>
                        <tr>
                            <td><strong style="font-family:monospace;"><?= htmlspecialchars($rf['reversal_ref']); ?></strong></td>
                            <td><?= htmlspecialchars($rf['booking_ref']); ?> (Seat #<?= $rf['seat_number']; ?>)</td>
                            <td>
                                <?= htmlspecialchars($rf['full_name']); ?><br>
                                <small style="color:var(--slate);"><?= htmlspecialchars($rf['mpesa_phone']); ?></small>
                            </td>
                            <td><?= format_kes($rf['original_amount']); ?></td>
                            <td><span style="color:var(--primary); font-weight:700;">+<?= format_kes($rf['cancellation_fee']); ?></span></td>
                            <td><span style="color:var(--danger); font-weight:700;">-<?= format_kes($rf['refund_amount']); ?></span></td>
                            <td><small><?= date('d M Y, h:i A', strtotime($rf['refunded_at'])); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
