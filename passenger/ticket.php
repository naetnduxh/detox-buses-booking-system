<?php
require_once __DIR__ . '/../config/db.php';

require_login();
$user = current_user();

$ref = trim($_GET['ref'] ?? '');
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($ref) && $booking_id <= 0) {
    set_flash('error', 'Invalid ticket reference.');
    header('Location: /detox_buses/passenger/my_bookings.php');
    exit;
}

// Fetch booking with route, bus, and passenger details
$sql = "SELECT bk.*, r.origin, r.destination, r.travel_date, r.departure_time, r.fare,
               b.bus_name, b.plate_number, b.bus_type,
               u.full_name, u.email, u.phone
        FROM bookings bk
        JOIN routes r ON bk.route_id = r.route_id
        JOIN buses b ON r.bus_id = b.bus_id
        JOIN users u ON bk.user_id = u.user_id
        WHERE ";

if (!empty($ref)) {
    $sql .= "bk.booking_ref = ? LIMIT 1";
    $params = [$ref];
} else {
    $sql .= "bk.booking_id = ? LIMIT 1";
    $params = [$booking_id];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ticket = $stmt->fetch();

if (!$ticket) {
    set_flash('error', 'Ticket not found.');
    header('Location: /detox_buses/passenger/my_bookings.php');
    exit;
}

// Ensure security: only ticket owner or admin can view
if (!is_admin() && $ticket['user_id'] != $user['id']) {
    set_flash('error', 'You are not authorized to view this ticket.');
    header('Location: /detox_buses/passenger/my_bookings.php');
    exit;
}

// Check time remaining
$cashout_check = check_cashout_eligibility($ticket['travel_date'], $ticket['departure_time']);

$page_title = "E-Ticket #" . $ticket['booking_ref'];
require_once __DIR__ . '/../config/header.php';
?>

<div class="no-print" style="max-width: 650px; margin: 1rem auto; display: flex; justify-content: space-between; align-items: center;">
    <a href="/detox_buses/passenger/my_bookings.php" style="color: var(--slate); text-decoration: none; font-weight: 700;">
        ← Back to My Bookings
    </a>
    <button onclick="window.print()" class="btn btn-secondary btn-sm btn-print">
        🖨️ Print / Download PDF
    </button>
</div>

<div class="ticket-wrapper">
    <!-- Ticket Header -->
    <div class="ticket-header">
        <div>
            <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85;">Digital Boarding Pass</div>
            <h1>Detox Buses Kenya</h1>
            <small style="opacity: 0.9;">Safe & Smart Intercity Transit</small>
        </div>
        <div style="text-align: right;">
            <div class="ticket-ref-badge"><?= htmlspecialchars($ticket['booking_ref']); ?></div>
            <div style="margin-top: 5px;">
                <?php if ($ticket['status'] === 'Confirmed'): ?>
                    <span class="badge badge-success">✓ CONFIRMED</span>
                <?php else: ?>
                    <span class="badge badge-danger">CASHED OUT / REFUNDED</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ticket Body -->
    <div class="ticket-body">
        <div class="ticket-grid">
            <div class="ticket-item">
                <span class="lbl">Passenger Name</span>
                <span class="val"><?= htmlspecialchars($ticket['full_name']); ?></span>
                <small style="color:var(--slate);"><?= htmlspecialchars($ticket['phone']); ?></small>
            </div>

            <div class="ticket-item">
                <span class="lbl">Assigned Seat</span>
                <span class="val" style="color: var(--primary); font-size: 1.6rem;">Seat #<?= $ticket['seat_number']; ?></span>
            </div>

            <div class="ticket-item">
                <span class="lbl">Origin & Destination</span>
                <span class="val"><?= htmlspecialchars($ticket['origin']); ?> ➔ <?= htmlspecialchars($ticket['destination']); ?></span>
            </div>

            <div class="ticket-item">
                <span class="lbl">Bus Fleet & Plate</span>
                <span class="val"><?= htmlspecialchars($ticket['bus_name']); ?></span>
                <small style="color:var(--slate);"><?= htmlspecialchars($ticket['plate_number']); ?> (<?= htmlspecialchars($ticket['bus_type']); ?>)</small>
            </div>

            <div class="ticket-item">
                <span class="lbl">Travel Date</span>
                <span class="val"><?= format_date($ticket['travel_date']); ?></span>
            </div>

            <div class="ticket-item">
                <span class="lbl">Departure Time</span>
                <span class="val" style="color:var(--primary);"><?= format_time($ticket['departure_time']); ?></span>
            </div>

            <div class="ticket-item" style="grid-column: span 2;">
                <span class="lbl">Selected Roadside Pickup Stage</span>
                <span class="val" style="font-size: 1.15rem; color: #b45309;">
                    📍 <?= htmlspecialchars($ticket['boarding_point']); ?>
                </span>
                <small style="color:var(--slate);">Please arrive at this stage 15 minutes before the departure time.</small>
            </div>
        </div>

        <div class="ticket-perforated-line"></div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="lbl" style="font-size:0.75rem; text-transform:uppercase; color:var(--slate); font-weight:700;">Fare Paid (M-Pesa)</span>
                <div style="font-size: 1.4rem; font-weight: 800; color: var(--dark);">
                    <?= format_kes($ticket['amount_paid']); ?>
                </div>
                <small style="color: var(--slate); font-family: monospace;">Ref: <?= htmlspecialchars($ticket['mpesa_code']); ?></small>
            </div>

            <div style="text-align: right;">
                <div class="barcode-sim" title="<?= htmlspecialchars($ticket['booking_ref']); ?>"></div>
                <small style="font-family: monospace; color: var(--slate);"><?= htmlspecialchars($ticket['booking_ref']); ?></small>
            </div>
        </div>
    </div>

    <!-- Ticket Footer -->
    <div class="ticket-footer">
        <small style="color: var(--slate);">
            Detox Buses Customer Support: <strong>+254 700 000 000</strong> &bull; Present this ticket on boarding.
        </small>
        
        <?php if ($ticket['status'] === 'Confirmed'): ?>
            <div class="no-print">
                <?php if ($cashout_check['is_eligible']): ?>
                    <a href="/detox_buses/passenger/my_bookings.php" class="btn btn-outline-danger btn-sm">
                        Cash Out (<?= $cashout_check['minutes_remaining']; ?>m remaining)
                    </a>
                <?php else: ?>
                    <span class="badge badge-warning">Locked: Within 1 hr</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
