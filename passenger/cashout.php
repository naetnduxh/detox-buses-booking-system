<?php
require_once __DIR__ . '/../config/db.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /detox_buses/passenger/my_bookings.php');
    exit;
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$mpesa_phone = trim($_POST['mpesa_phone'] ?? $user['phone']);

if ($booking_id <= 0) {
    set_flash('error', 'Invalid booking selected for cash-out.');
    header('Location: /detox_buses/passenger/my_bookings.php');
    exit;
}


$stmt = $pdo->prepare("SELECT bk.*, r.travel_date, r.departure_time, r.origin, r.destination 
                       FROM bookings bk
                       JOIN routes r ON bk.route_id = r.route_id
                       WHERE bk.booking_id = ? AND bk.user_id = ? LIMIT 1");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('error', 'Booking record not found or does not belong to your account.');
    header('Location: /detox_buses/passenger/my_bookings.php');
    exit;
}

if ($booking['status'] !== 'Confirmed') {
    set_flash('error', 'This ticket has already been cashed out or refunded.');
    header('Location: /detox_buses/passenger/my_bookings.php');
    exit;
}


$eligibility = check_cashout_eligibility($booking['travel_date'], $booking['departure_time']);

if (!$eligibility['is_eligible']) {
    set_flash('error', 'Cash-out rejected: ' . $eligibility['reason']);
    header('Location: /detox_buses/passenger/my_bookings.php');
    exit;
}


$original_fare = (float)$booking['amount_paid'];
$cancellation_fee = round($original_fare * 0.10, 2);
$refund_amount = round($original_fare * 0.90, 2);


do {
    $reversal_ref = 'REV-' . date('Y') . '-' . mt_rand(1000, 9999);
    $stmt_check = $pdo->prepare("SELECT refund_id FROM refunds WHERE reversal_ref = ?");
    $stmt_check->execute([$reversal_ref]);
} while ($stmt_check->fetch());

try {
    $pdo->beginTransaction();


    $stmt_update = $pdo->prepare("UPDATE bookings SET status = 'Refunded' WHERE booking_id = ?");
    $stmt_update->execute([$booking_id]);

   
    $stmt_refund = $pdo->prepare("INSERT INTO refunds (booking_id, original_amount, cancellation_fee, refund_amount, mpesa_phone, reversal_ref) 
                                 VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_refund->execute([
        $booking_id,
        $original_fare,
        $cancellation_fee,
        $refund_amount,
        $mpesa_phone,
        $reversal_ref
    ]);

    $pdo->commit();

    set_flash('success', "✓ Cash-out successful! Refund of " . format_kes($refund_amount) . " reversed to {$mpesa_phone} (Reversal Code: {$reversal_ref}). 10% cancellation fee (" . format_kes($cancellation_fee) . ") deducted. Seat #{$booking['seat_number']} has been released.");
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'An error occurred while processing your cash-out. Please try again.');
}

header('Location: /detox_buses/passenger/my_bookings.php');
exit;
