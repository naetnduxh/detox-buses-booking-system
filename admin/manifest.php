<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$route_id = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;

// Fetch all available routes for dropdown
$all_routes = $pdo->query("SELECT r.route_id, r.origin, r.destination, r.travel_date, r.departure_time, b.bus_name, b.plate_number
                          FROM routes r
                          JOIN buses b ON r.bus_id = b.bus_id
                          ORDER BY r.travel_date DESC, r.departure_time ASC")->fetchAll();

if ($route_id <= 0 && !empty($all_routes)) {
    $route_id = $all_routes[0]['route_id'];
}

// Fetch chosen route details
$stmt_r = $pdo->prepare("SELECT r.*, b.bus_name, b.plate_number, b.total_seats, b.bus_type
                         FROM routes r
                         JOIN buses b ON r.bus_id = b.bus_id
                         WHERE r.route_id = ? LIMIT 1");
$stmt_r->execute([$route_id]);
$current_route = $stmt_r->fetch();

// Fetch confirmed passenger bookings ordered by seat_number
$passengers = [];
if ($current_route) {
    $stmt_p = $pdo->prepare("SELECT bk.*, u.full_name, u.email, u.phone
                             FROM bookings bk
                             JOIN users u ON bk.user_id = u.user_id
                             WHERE bk.route_id = ? AND bk.status = 'Confirmed'
                             ORDER BY bk.seat_number ASC");
    $stmt_p->execute([$route_id]);
    $passengers = $stmt_p->fetchAll();
}

$page_title = "Passenger Manifest - " . ($current_route ? $current_route['destination'] : '');
require_once __DIR__ . '/../config/header.php';
?>

<div class="no-print" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <a href="/detox_buses/admin/dashboard.php" style="color:var(--slate); text-decoration:none; font-weight:700;">
            ← Back to Dashboard
        </a>
        <h2 style="margin-top: 0.3rem;">Official Passenger Manifest</h2>
    </div>

    <!-- Route Selector -->
    <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
        <label for="route_id" style="font-weight: 700; font-size: 0.9rem;">Select Trip:</label>
        <select name="route_id" id="route_id" class="form-control" style="width: auto;" onchange="this.form.submit()">
            <?php foreach ($all_routes as $r): ?>
                <option value="<?= $r['route_id']; ?>" <?= ($r['route_id'] == $route_id) ? 'selected' : ''; ?>>
                    <?= format_date($r['travel_date']); ?> <?= format_time($r['departure_time']); ?> - <?= htmlspecialchars($r['destination']); ?> (<?= htmlspecialchars($r['bus_name']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm btn-print">
            🖨️ Print Manifest
        </button>
    </form>
</div>

<?php if (!$current_route): ?>
    <div class="card" style="text-align: center; padding: 3rem;">
        <h3>No schedule selected.</h3>
    </div>
<?php else: ?>
    <div class="card" style="border: 2px solid #cbd5e1; padding: 2rem;">
        
        <!-- Manifest Official Header -->
        <div style="border-bottom: 2px solid var(--dark); padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--primary);">DETOX BUSES KENYA</h1>
                <div style="font-size: 1.1rem; font-weight: 700; text-transform: uppercase;">
                    Official Passenger Boarding Manifest
                </div>
                <small style="color: var(--slate);">Course BIT 313 &bull; Generated: <?= date('d M Y, h:i A'); ?></small>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 1.2rem; font-weight: 800;">
                    <?= htmlspecialchars($current_route['origin']); ?> ➔ <?= htmlspecialchars($current_route['destination']); ?>
                </div>
                <div><strong>Date:</strong> <?= format_date($current_route['travel_date']); ?> at <?= format_time($current_route['departure_time']); ?></div>
                <div><strong>Bus:</strong> <?= htmlspecialchars($current_route['bus_name']); ?> (<?= htmlspecialchars($current_route['plate_number']); ?>)</div>
            </div>
        </div>

        <!-- Manifest Statistics -->
        <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem; background: #f8fafc; padding: 0.8rem 1.2rem; border-radius: 8px; border: 1px solid var(--border);">
            <div><strong>Capacity:</strong> <?= $current_route['total_seats']; ?> Seats</div>
            <div><strong>Confirmed Passengers:</strong> <span style="color:var(--success); font-weight:800;"><?= count($passengers); ?></span></div>
            <div><strong>Available Empty Seats:</strong> <?= $current_route['total_seats'] - count($passengers); ?></div>
            <div><strong>Standard Fare:</strong> <?= format_kes($current_route['fare']); ?></div>
        </div>

        <!-- Passenger Table -->
        <div class="table-responsive">
            <table class="table" style="border: 1px solid #cbd5e1;">
                <thead>
                    <tr style="background: #e2e8f0;">
                        <th style="width: 70px;">Seat #</th>
                        <th>Passenger Name</th>
                        <th>Phone Number</th>
                        <th>Roadside Boarding Stage</th>
                        <th>Booking Ref</th>
                        <th>M-Pesa Code</th>
                        <th>Boarded?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($passengers)): ?>
                        <tr><td colspan="7" style="text-align:center; padding: 2rem; color:var(--slate);">No confirmed passengers for this trip yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($passengers as $p): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <strong style="font-size: 1.2rem; color: var(--primary);">#<?= $p['seat_number']; ?></strong>
                                </td>
                                <td><strong><?= htmlspecialchars($p['full_name']); ?></strong></td>
                                <td><?= htmlspecialchars($p['phone']); ?></td>
                                <td>
                                    <strong style="color: #b45309;">📍 <?= htmlspecialchars($p['boarding_point']); ?></strong>
                                </td>
                                <td style="font-family: monospace;"><?= htmlspecialchars($p['booking_ref']); ?></td>
                                <td style="font-family: monospace; font-weight: 700;"><?= htmlspecialchars($p['mpesa_code']); ?></td>
                                <td style="text-align: center;">
                                    <div style="width: 20px; height: 20px; border: 2px solid #94a3b8; border-radius: 4px; margin: 0 auto;"></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Driver / Conductor Sign-off Section -->
        <div style="margin-top: 3rem; display: flex; justify-content: space-between; border-top: 1px dashed #94a3b8; padding-top: 1.5rem; font-size: 0.9rem;">
            <div>
                <strong>Driver Name:</strong> _____________________________ 
                &nbsp;&nbsp;&nbsp;&nbsp; 
                <strong>Sign:</strong> __________________
            </div>
            <div>
                <strong>Conductor / Dispatcher:</strong> _____________________________ 
                &nbsp;&nbsp;&nbsp;&nbsp; 
                <strong>Sign:</strong> __________________
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
