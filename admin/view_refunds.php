<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$page_title = "Cash-Out & Refund Audit Trail";


$sql = "SELECT rf.*, bk.booking_ref, bk.seat_number, bk.boarding_point,
               u.full_name, u.email,
               r.origin, r.destination, r.travel_date, r.departure_time,
               b.bus_name, b.plate_number
        FROM refunds rf
        JOIN bookings bk ON rf.booking_id = bk.booking_id
        JOIN users u ON bk.user_id = u.user_id
        JOIN routes r ON bk.route_id = r.route_id
        JOIN buses b ON r.bus_id = b.bus_id
        ORDER BY rf.refunded_at DESC";

$refunds = $pdo->query($sql)->fetchAll();


$total_refunds_count = count($refunds);
$total_original = 0;
$total_fees = 0;
$total_refunded = 0;

foreach ($refunds as $rf) {
    $total_original += (float)$rf['original_amount'];
    $total_fees += (float)$rf['cancellation_fee'];
    $total_refunded += (float)$rf['refund_amount'];
}

require_once __DIR__ . '/../config/header.php';
?>

<div class="card-header">
    <div>
        <h2>Cash-Out & Refund Audit Trail</h2>
        <p style="color:var(--slate); font-size:0.9rem;">
            Automated 10% Cancellation Fee & 90% M-Pesa Reversal Audit Logs (Course BIT 313).
        </p>
    </div>
    <a href="/detox_buses/admin/dashboard.php" style="color:var(--slate); text-decoration:none; font-weight:700;">
        ← Back to Dashboard
    </a>
</div>

<!-- Financial Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="margin-bottom:0; padding:1.2rem; border-left: 4px solid var(--primary);">
        <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Total Transactions</small>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary); margin: 0.3rem 0;">
            <?= $total_refunds_count; ?> Cashed Out
        </div>
        <small style="color:var(--slate);">1-Hour Pre-Departure Rule</small>
    </div>

    <div class="card" style="margin-bottom:0; padding:1.2rem; border-left: 4px solid var(--slate);">
        <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Original Gross Value</small>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--dark); margin: 0.3rem 0;">
            <?= format_kes($total_original); ?>
        </div>
        <small style="color:var(--slate);">Face value of cancelled seats</small>
    </div>

    <div class="card" style="margin-bottom:0; padding:1.2rem; border-left: 4px solid var(--success);">
        <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">10% Cancellation Fees Retained</small>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--success); margin: 0.3rem 0;">
            <?= format_kes($total_fees); ?>
        </div>
        <small style="color:var(--slate);">Company earned revenue</small>
    </div>

    <div class="card" style="margin-bottom:0; padding:1.2rem; border-left: 4px solid var(--danger);">
        <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">90% Reversed via M-Pesa</small>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--danger); margin: 0.3rem 0;">
            <?= format_kes($total_refunded); ?>
        </div>
        <small style="color:var(--slate);">Sent back to passenger phones</small>
    </div>
</div>

<!-- Full Audit Log Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="padding: 1.2rem; border-bottom: 1px solid var(--border);">
        <h3 style="font-size: 1.15rem;">Transaction Ledger</h3>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Audit ID</th>
                    <th>Reversal Ref</th>
                    <th>Booking Ref</th>
                    <th>Passenger</th>
                    <th>Cancelled Trip</th>
                    <th>Original Fare</th>
                    <th>10% Fee Retained</th>
                    <th>90% Refund Amount</th>
                    <th>Credited Phone</th>
                    <th>Audit Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($refunds)): ?>
                    <tr><td colspan="10" style="text-align:center; color:var(--slate); padding: 2rem;">No cash-outs recorded in system yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($refunds as $rf): ?>
                        <tr>
                            <td>#<?= $rf['refund_id']; ?></td>
                            <td><strong style="font-family: monospace; color: var(--primary);"><?= htmlspecialchars($rf['reversal_ref']); ?></strong></td>
                            <td><?= htmlspecialchars($rf['booking_ref']); ?> (Seat #<?= $rf['seat_number']; ?>)</td>
                            <td>
                                <strong><?= htmlspecialchars($rf['full_name']); ?></strong><br>
                                <small style="color:var(--slate);"><?= htmlspecialchars($rf['email']); ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($rf['origin']); ?> ➔ <?= htmlspecialchars($rf['destination']); ?><br>
                                <small style="color:var(--slate);"><?= format_date($rf['travel_date']); ?> <?= format_time($rf['departure_time']); ?></small>
                            </td>
                            <td><?= format_kes($rf['original_amount']); ?></td>
                            <td><span style="color:var(--success); font-weight:800;">+<?= format_kes($rf['cancellation_fee']); ?></span></td>
                            <td><span style="color:var(--danger); font-weight:800;">-<?= format_kes($rf['refund_amount']); ?></span></td>
                            <td><strong><?= htmlspecialchars($rf['mpesa_phone']); ?></strong></td>
                            <td><small><?= date('d M Y, h:i A', strtotime($rf['refunded_at'])); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
