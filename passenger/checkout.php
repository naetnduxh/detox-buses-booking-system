<?php
require_once __DIR__ . '/../config/db.php';

require_login();
$user = current_user();

$route_id = isset($_REQUEST['route_id']) ? (int)$_REQUEST['route_id'] : 0;
$seat_number = isset($_REQUEST['selected_seat']) ? (int)$_REQUEST['selected_seat'] : 0;
$boarding_point = trim($_REQUEST['boarding_point'] ?? '');

if ($route_id <= 0 || $seat_number <= 0) {
    set_flash('error', 'Please choose a trip schedule and a seat.');
    header('Location: /detox_buses/index.php');
    exit;
}

// Fetch route and bus
$stmt = $pdo->prepare("SELECT r.*, b.bus_name, b.plate_number, b.bus_type
                       FROM routes r
                       JOIN buses b ON r.bus_id = b.bus_id
                       WHERE r.route_id = ? AND r.status = 'Active' LIMIT 1");
$stmt->execute([$route_id]);
$route = $stmt->fetch();

if (!$route) {
    set_flash('error', 'The selected trip is no longer active.');
    header('Location: /detox_buses/index.php');
    exit;
}

// Double-booking check
$check_seat = $pdo->prepare("SELECT booking_id FROM bookings WHERE route_id = ? AND seat_number = ? AND status = 'Confirmed' LIMIT 1");
$check_seat->execute([$route_id, $seat_number]);
if ($check_seat->fetch()) {
    set_flash('error', "Seat #{$seat_number} has just been booked by another passenger. Please select another seat.");
    header("Location: /detox_buses/passenger/seat_map.php?route_id={$route_id}");
    exit;
}

// Process Payment Submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
    $mpesa_code = strtoupper(trim($_POST['mpesa_code'] ?? ''));
    $mpesa_phone = trim($_POST['mpesa_phone'] ?? '');

    if (empty($mpesa_code)) {
        $error = "Please enter or generate a simulated M-Pesa transaction code.";
    } elseif (empty($mpesa_phone)) {
        $error = "Please provide an M-Pesa phone number.";
    } else {
        // Re-check seat one more time in atomic block
        $check_seat->execute([$route_id, $seat_number]);
        if ($check_seat->fetch()) {
            $error = "Seat #{$seat_number} was booked while processing. Please pick another seat.";
        } else {
            // Generate unique booking reference: DTX-YYYY-XXXX
            do {
                $ref = 'DTX-' . date('Y') . '-' . mt_rand(1000, 9999);
                $stmt_ref = $pdo->prepare("SELECT booking_id FROM bookings WHERE booking_ref = ?");
                $stmt_ref->execute([$ref]);
            } while ($stmt_ref->fetch());

            $insert = $pdo->prepare("INSERT INTO bookings (booking_ref, user_id, route_id, seat_number, boarding_point, amount_paid, mpesa_code, status) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmed')");
            $success = $insert->execute([
                $ref,
                $user['id'],
                $route_id,
                $seat_number,
                $boarding_point,
                $route['fare'],
                $mpesa_code
            ]);

            if ($success) {
                set_flash('success', "Booking confirmed! Your ticket has been generated.");
                header("Location: /detox_buses/passenger/ticket.php?ref=" . urlencode($ref));
                exit;
            } else {
                $error = "Failed to record booking. Please try again.";
            }
        }
    }
}

$page_title = "Checkout & M-Pesa Payment";
require_once __DIR__ . '/../config/header.php';
?>

<div style="max-width: 650px; margin: 1rem auto;">
    <div style="margin-bottom: 1rem;">
        <a href="/detox_buses/passenger/seat_map.php?route_id=<?= $route['route_id']; ?>" style="color: var(--slate); text-decoration: none; font-weight: 700;">
            ← Change Seat Selection
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>M-Pesa Checkout & Confirmation</h2>
            <span class="badge badge-success">Step 3 of 3</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠</span>
                <span><?= htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Booking Breakdown -->
        <div style="background: #f8fafc; border-radius: 10px; border: 1px solid var(--border); padding: 1.2rem; margin-bottom: 1.5rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Trip</small>
                    <div style="font-weight: 800; font-size: 1.05rem;"><?= htmlspecialchars($route['origin']); ?> ➔ <?= htmlspecialchars($route['destination']); ?></div>
                    <small style="color:var(--slate);"><?= htmlspecialchars($route['bus_name']); ?> (<?= htmlspecialchars($route['plate_number']); ?>)</small>
                </div>
                <div>
                    <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Date & Time</small>
                    <div style="font-weight: 800;"><?= format_date($route['travel_date']); ?></div>
                    <small style="color:var(--primary); font-weight:700;"><?= format_time($route['departure_time']); ?></small>
                </div>
                <div>
                    <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Seat Number</small>
                    <div style="font-weight: 800; font-size: 1.2rem; color: var(--primary);">Seat #<?= $seat_number; ?></div>
                </div>
                <div>
                    <small style="color:var(--slate); text-transform:uppercase; font-weight:700;">Roadside Boarding Stage</small>
                    <div style="font-weight: 800; color: var(--dark);"><?= htmlspecialchars($boarding_point); ?></div>
                </div>
            </div>

            <div style="border-top: 2px dashed #cbd5e1; margin-top: 1rem; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 1.1rem; font-weight: 700;">Total Payable Fare:</span>
                <span style="font-size: 1.5rem; font-weight: 800; color: var(--primary);">
                    <?= format_kes($route['fare']); ?>
                </span>
            </div>
        </div>

        <!-- Simulated M-Pesa Payment Box -->
        <form method="POST" action="">
            <input type="hidden" name="action" value="confirm_payment">
            <input type="hidden" name="route_id" value="<?= $route['route_id']; ?>">
            <input type="hidden" name="selected_seat" value="<?= $seat_number; ?>">
            <input type="hidden" name="boarding_point" value="<?= htmlspecialchars($boarding_point); ?>">

            <div style="background: #f0fdf4; border: 1.5px solid #22c55e; border-radius: 10px; padding: 1.2rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem;">
                    <span style="font-size: 1.4rem;">📲</span>
                    <strong style="color: #15803d; font-size: 1.1rem;">Simulated M-Pesa Gateway</strong>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="mpesa_phone">M-Pesa Phone Number</label>
                    <input type="text" id="mpesa_phone" name="mpesa_phone" class="form-control" required value="<?= htmlspecialchars($_POST['mpesa_phone'] ?? $user['phone']); ?>">
                </div>

                <div class="form-group" style="margin-bottom: 0.8rem;">
                    <label for="mpesa_code">Simulated M-Pesa Transaction Code</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="mpesa_code" name="mpesa_code" class="form-control" placeholder="e.g. QBC89218LK" required value="<?= htmlspecialchars($_POST['mpesa_code'] ?? ''); ?>" style="text-transform: uppercase; font-family: monospace; font-size: 1.1rem; font-weight: bold;">
                        <button type="button" id="btn_generate_mpesa" class="btn btn-secondary" style="white-space: nowrap;">
                            ⚡ Auto-Generate Code
                        </button>
                    </div>
                    <small style="color: var(--slate); font-size: 0.8rem; margin-top: 4px;">
                        Click Auto-Generate or type any simulated 10-character code.
                    </small>
                </div>
            </div>

            <!-- Guarantee Note -->
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 0.8rem 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #92400e;">
                ⚠️ <strong>Detox Buses 1-Hour Cash-Out Policy:</strong> If your plans change, you can click <em>Cancel & Cash Out</em> on your dashboard up to 60 minutes before departure for an automated 90% instant refund (10% cancellation fee).
            </div>

            <button type="submit" class="btn btn-accent" style="width: 100%; font-size: 1.1rem; padding: 0.9rem;">
                ✓ Confirm & Pay <?= format_kes($route['fare']); ?>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
