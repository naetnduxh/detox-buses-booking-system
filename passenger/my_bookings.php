<?php
require_once __DIR__ . '/../config/db.php';

require_login();
$user = current_user();


$stmt = $pdo->prepare("SELECT bk.*, r.origin, r.destination, r.travel_date, r.departure_time, r.fare,
                              b.bus_name, b.plate_number, b.bus_type,
                              rf.refund_amount, rf.cancellation_fee, rf.reversal_ref, rf.refunded_at
                       FROM bookings bk
                       JOIN routes r ON bk.route_id = r.route_id
                       JOIN buses b ON r.bus_id = b.bus_id
                       LEFT JOIN refunds rf ON bk.booking_id = rf.booking_id
                       WHERE bk.user_id = ?
                       ORDER BY bk.created_at DESC");
$stmt->execute([$user['id']]);
$bookings = $stmt->fetchAll();

$page_title = "My Bookings & Self-Service Cash-Out";
require_once __DIR__ . '/../config/header.php';
?>

<div class="card-header">
    <div>
        <h2>My Bookings & Tickets</h2>
        <p style="color:var(--slate); font-size:0.9rem;">
            Review your confirmed trips, print e-tickets, or cash-out online up to 1 hour prior to departure.
        </p>
    </div>
    <a href="/detox_buses/index.php" class="btn btn-primary btn-sm">+ Book Another Trip</a>
</div>

<?php if (empty($bookings)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🎫</div>
        <h3>You have no active or past bookings.</h3>
        <p style="color:var(--slate); margin-bottom: 1.5rem;">Explore available schedules to book your next trip with Detox Buses.</p>
        <a href="/detox_buses/index.php" class="btn btn-primary">Find Available Buses</a>
    </div>
<?php else: ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Trip & Bus</th>
                        <th>Travel Date & Time</th>
                        <th>Seat</th>
                        <th>Pickup Stage</th>
                        <th>Amount Paid</th>
                        <th>Cash-Out Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $bk): ?>
                        <?php 
                            $time_check = check_cashout_eligibility($bk['travel_date'], $bk['departure_time']); 
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($bk['booking_ref']); ?></strong><br>
                                <small style="color:var(--slate); font-family: monospace;"><?= htmlspecialchars($bk['mpesa_code']); ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($bk['origin']); ?> ➔ <?= htmlspecialchars($bk['destination']); ?></strong><br>
                                <small style="color:var(--slate);"><?= htmlspecialchars($bk['bus_name']); ?> (<?= htmlspecialchars($bk['plate_number']); ?>)</small>
                            </td>
                            <td>
                                <div><?= format_date($bk['travel_date']); ?></div>
                                <strong style="color: var(--primary);"><?= format_time($bk['departure_time']); ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-info" style="font-size: 0.9rem;">Seat #<?= $bk['seat_number']; ?></span>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#b45309; font-weight:700;">
                                    📍 <?= htmlspecialchars($bk['boarding_point']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= format_kes($bk['amount_paid']); ?></strong>
                            </td>
                            <td>
                                <?php if ($bk['status'] === 'Confirmed'): ?>
                                    <?php if ($time_check['is_eligible']): ?>
                                        <span class="badge badge-success" title="<?= $time_check['minutes_remaining']; ?> mins until departure">
                                            ✓ Eligible (<?= $time_check['minutes_remaining']; ?>m left)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" title="<?= htmlspecialchars($time_check['reason']); ?>">
                                            🔒 <?= htmlspecialchars($time_check['reason']); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-danger">Cashed Out</span>
                                    <div style="font-size: 0.75rem; color: var(--slate); margin-top: 2px;">
                                        Refunded <?= format_kes($bk['refund_amount']); ?><br>
                                        <span style="font-family: monospace;"><?= htmlspecialchars($bk['reversal_ref']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                                    <a href="/detox_buses/passenger/ticket.php?ref=<?= urlencode($bk['booking_ref']); ?>" class="btn btn-secondary btn-sm">
                                        View Ticket
                                    </a>

                                    <?php if ($bk['status'] === 'Confirmed'): ?>
                                        <?php if ($time_check['is_eligible']): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm btn-open-cashout"
                                                    data-booking-id="<?= $bk['booking_id']; ?>"
                                                    data-ref="<?= htmlspecialchars($bk['booking_ref']); ?>"
                                                    data-fare="<?= $bk['amount_paid']; ?>"
                                                    data-phone="<?= htmlspecialchars($user['phone']); ?>">
                                                Cash Out (90%)
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm btn-disabled" disabled title="Cash-out is locked within 1 hour of departure">
                                                Cash-out Closed
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- 1-Hour Pre-Departure Cash-Out Modal -->
<div class="modal-overlay" id="cashout_modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Confirm Ticket Cash-Out</h3>
            <button type="button" class="close-modal-btn">&times;</button>
        </div>

        <p style="color:var(--slate); font-size:0.95rem;">
            You are initiating a self-service cancellation for booking reference 
            <strong id="modal_ref_display" style="color:var(--dark);"></strong>.
        </p>

        <!-- Dynamic 10% Fee & 90% Refund Math Breakdown -->
        <div class="fee-breakdown">
            <div class="fee-row">
                <span>Original Fare Paid:</span>
                <span id="modal_orig_fare" style="font-weight:700;"></span>
            </div>
            <div class="fee-row" style="color: var(--danger);">
                <span>Automated Cancellation Fee (10%):</span>
                <span id="modal_fee" style="font-weight:700;"></span>
            </div>
            <div class="fee-row total-refund">
                <span>Instant Cash-Out Refund (90%):</span>
                <span id="modal_refund"></span>
            </div>
        </div>

        <form method="POST" action="/detox_buses/passenger/cashout.php">
            <input type="hidden" name="booking_id" id="modal_booking_id" value="">

            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label for="modal_phone">M-Pesa Refund Recipient Phone</label>
                <input type="text" name="mpesa_phone" id="modal_phone" class="form-control" required>
                <small style="color:var(--slate); font-size:0.75rem;">Simulated 90% reversal will be credited to this number.</small>
            </div>

            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 0.75rem; font-size: 0.8rem; color: #065f46; margin-bottom: 1.2rem;">
                ✓ Your seat will immediately turn green on the bus map so another passenger can book it.
            </div>

            <div style="display: flex; gap: 0.8rem;">
                <button type="button" class="btn btn-secondary" id="btn_close_modal" style="flex: 1;">Cancel</button>
                <button type="submit" class="btn btn-danger" style="flex: 2;">Confirm Cash-Out (90%)</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
