<?php
require_once __DIR__ . '/config/db.php';

$page_title = "Book Detox Buses Online - Nairobi Intercity Routes";

// Filter parameters
$destination = trim($_GET['destination'] ?? '');
$travel_date = trim($_GET['travel_date'] ?? date('Y-m-d'));

// Build query for available routes
$sql = "SELECT r.*, b.bus_name, b.plate_number, b.bus_type, b.total_seats,
        (b.total_seats - (
            SELECT COUNT(*) FROM bookings bk 
            WHERE bk.route_id = r.route_id AND bk.status = 'Confirmed'
        )) AS seats_available
        FROM routes r
        JOIN buses b ON r.bus_id = b.bus_id
        WHERE r.status = 'Active'";

$params = [];

if (!empty($destination)) {
    $sql .= " AND r.destination = ?";
    $params[] = $destination;
}

if (!empty($travel_date)) {
    $sql .= " AND r.travel_date = ?";
    $params[] = $travel_date;
} else {
    // If date left empty, show all future trips
    $sql .= " AND r.travel_date >= CURDATE()";
}

$sql .= " ORDER BY r.travel_date ASC, r.departure_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$available_trips = $stmt->fetchAll();

// If exact search returned empty, find nearest upcoming dates
$alternative_trips = [];
if (empty($available_trips)) {
    $alt_sql = "SELECT r.*, b.bus_name, b.plate_number, b.bus_type, b.total_seats,
            (b.total_seats - (
                SELECT COUNT(*) FROM bookings bk 
                WHERE bk.route_id = r.route_id AND bk.status = 'Confirmed'
            )) AS seats_available
            FROM routes r
            JOIN buses b ON r.bus_id = b.bus_id
            WHERE r.status = 'Active' AND r.travel_date >= CURDATE()";
    $alt_params = [];
    if (!empty($destination)) {
        $alt_sql .= " AND r.destination = ?";
        $alt_params[] = $destination;
    }
    $alt_sql .= " ORDER BY r.travel_date ASC, r.departure_time ASC LIMIT 8";
    $alt_stmt = $pdo->prepare($alt_sql);
    $alt_stmt->execute($alt_params);
    $alternative_trips = $alt_stmt->fetchAll();
}

require_once __DIR__ . '/config/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <h1>Detox Buses Online Booking</h1>
    <p>Travel with confidence across Kenya. Search daily schedules, pick your exact seats, select flexible roadside boarding points, and enjoy self-service 90% instant cash-out up to 1 hour before departure.</p>
</section>

<!-- Search Box -->
<section class="search-card">
    <form method="GET" action="" class="search-form">
        <div class="form-group">
            <label for="origin">Departure From</label>
            <input type="text" id="origin" name="origin" class="form-control" value="Nairobi (Capital)" readonly style="background:#f1f5f9; cursor:not-allowed;">
        </div>

        <div class="form-group">
            <label for="destination">Destination County</label>
            <select id="destination" name="destination" class="form-control">
                <option value="">All Destinations</option>
                <option value="Kakamega" <?= $destination === 'Kakamega' ? 'selected' : ''; ?>>Kakamega</option>
                <option value="Kisumu" <?= $destination === 'Kisumu' ? 'selected' : ''; ?>>Kisumu</option>
                <option value="Nakuru" <?= $destination === 'Nakuru' ? 'selected' : ''; ?>>Nakuru</option>
                <option value="Eldoret" <?= $destination === 'Eldoret' ? 'selected' : ''; ?>>Eldoret</option>
                <option value="Mombasa" <?= $destination === 'Mombasa' ? 'selected' : ''; ?>>Mombasa</option>
            </select>
        </div>

        <div class="form-group">
            <label for="travel_date">Travel Date</label>
            <input type="date" id="travel_date" name="travel_date" class="form-control" value="<?= htmlspecialchars($travel_date); ?>" min="<?= date('Y-m-d'); ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-accent" style="height: 48px; width:100%;">
                🔍 Find Trips
            </button>
        </div>
    </form>
</section>

<!-- Trips Results List -->
<section style="margin-top: 3rem;">
    <div class="card-header">
        <div>
            <h2>Available Detox Schedules</h2>
            <p style="color:var(--slate); font-size:0.9rem;">
                <?php if (!empty($travel_date)): ?>
                    Showing departures for <?= format_date($travel_date); ?> <?= !empty($destination) ? 'to ' . htmlspecialchars($destination) : ''; ?>
                <?php else: ?>
                    Showing all upcoming departures <?= !empty($destination) ? 'to ' . htmlspecialchars($destination) : ''; ?>
                <?php endif; ?>
            </p>
        </div>
        <span class="badge badge-info"><?= count($available_trips); ?> Trips Found</span>
    </div>

    <?php if (empty($available_trips)): ?>
        <div class="card" style="text-align: center; padding: 2.5rem 1.5rem; margin-bottom: 2rem;">
            <div style="font-size: 2.5rem; margin-bottom: 0.8rem;">🚌</div>
            <h3>No scheduled trips found on <?= format_date($travel_date); ?> <?= !empty($destination) ? 'for ' . htmlspecialchars($destination) : ''; ?>.</h3>
            <p style="color: var(--slate); margin-bottom: 1.2rem;">Below are the closest alternative dates with available Detox Buses:</p>
            <a href="/detox_buses/index.php?destination=<?= urlencode($destination); ?>&travel_date=" class="btn btn-secondary btn-sm">View All Upcoming Dates</a>
        </div>

        <?php if (!empty($alternative_trips)): ?>
            <div class="card-header" style="margin-top: 1rem;">
                <h3>Nearest Available Departures</h3>
                <span class="badge badge-success"><?= count($alternative_trips); ?> Alternate Schedules</span>
            </div>
            <div class="card" style="padding: 0; overflow: hidden;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Bus Fleet</th>
                                <th>Route</th>
                                <th>Departure Time</th>
                                <th>Pickup Stages</th>
                                <th>Available Seats</th>
                                <th>Fare</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alternative_trips as $trip): ?>
                                <?php $seats_left = (int)$trip['seats_available']; ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($trip['bus_name']); ?></strong><br>
                                        <small style="color:var(--slate);"><?= htmlspecialchars($trip['plate_number']); ?> &bull; <span class="badge badge-info"><?= htmlspecialchars($trip['bus_type']); ?></span></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($trip['origin']); ?></strong> ➔ <strong><?= htmlspecialchars($trip['destination']); ?></strong><br>
                                        <small style="color:var(--primary); font-weight:700;">📅 <?= format_date($trip['travel_date']); ?></small>
                                    </td>
                                    <td>
                                        <span style="font-size: 1.1rem; font-weight: 800; color: var(--primary);">
                                            <?= format_time($trip['departure_time']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="max-width: 250px; font-size: 0.85rem; color: var(--slate);">
                                            📍 <?= htmlspecialchars($trip['boarding_points']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($seats_left > 0): ?>
                                            <span class="badge badge-success"><?= $seats_left; ?> / <?= $trip['total_seats']; ?> Seats Free</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Sold Out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 1.15rem; font-weight: 800; color: var(--dark);">
                                            <?= format_kes($trip['fare']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($seats_left > 0): ?>
                                            <a href="/detox_buses/passenger/seat_map.php?route_id=<?= $trip['route_id']; ?>" class="btn btn-primary btn-sm">
                                                Select Seats ➔
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm btn-disabled" disabled>Fully Booked</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bus Fleet</th>
                            <th>Route</th>
                            <th>Departure Time</th>
                            <th>Pickup Stages</th>
                            <th>Available Seats</th>
                            <th>Fare</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_trips as $trip): ?>
                            <?php 
                                $seats_left = (int)$trip['seats_available']; 
                                $check = check_cashout_eligibility($trip['travel_date'], $trip['departure_time']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($trip['bus_name']); ?></strong><br>
                                    <small style="color:var(--slate);"><?= htmlspecialchars($trip['plate_number']); ?> &bull; <span class="badge badge-info"><?= htmlspecialchars($trip['bus_type']); ?></span></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($trip['origin']); ?></strong> ➔ <strong><?= htmlspecialchars($trip['destination']); ?></strong><br>
                                    <small style="color:var(--slate);"><?= format_date($trip['travel_date']); ?></small>
                                </td>
                                <td>
                                    <span style="font-size: 1.1rem; font-weight: 800; color: var(--primary);">
                                        <?= format_time($trip['departure_time']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="max-width: 250px; font-size: 0.85rem; color: var(--slate);">
                                        📍 <?= htmlspecialchars($trip['boarding_points']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($seats_left > 0): ?>
                                        <span class="badge badge-success"><?= $seats_left; ?> / <?= $trip['total_seats']; ?> Seats Free</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Sold Out (32/32)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 1.15rem; font-weight: 800; color: var(--dark);">
                                        <?= format_kes($trip['fare']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($seats_left > 0): ?>
                                        <a href="/detox_buses/passenger/seat_map.php?route_id=<?= $trip['route_id']; ?>" class="btn btn-primary btn-sm">
                                            Select Seats ➔
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm btn-disabled" disabled>Fully Booked</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- The 4 Competitive Upgrades -->
<section>
    <div style="text-align: center; margin-top: 4rem; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.8rem; font-weight: 800;">Special System Features</h2>
        <p style="color: var(--slate);">Built to solve traditional booking queues, rigid terminals, and lost fares.</p>
    </div>

    <div class="features-grid">
        <div class="feature-box">
            <div class="feature-icon">⏱️</div>
            <h3>1-Hour Self Cash-Out</h3>
            <p>Emergencies happen. Cancel your confirmed ticket online up to 60 minutes before departure without visiting an office.</p>
        </div>

        <div class="feature-box">
            <div class="feature-icon">💰</div>
            <h3>90% Instant Refund (10% Fee)</h3>
            <p>Fair automated cancellation fee calculation: 10% is retained and 90% is reversed instantly, instantly releasing your seat.</p>
        </div>

        <div class="feature-box">
            <div class="feature-icon">👥</div>
            <h3>Smart Group Seat Finder</h3>
            <p>Traveling with family or classmates? Use our 1-click helper to automatically identify and pick adjacent side-by-side seats.</p>
        </div>

        <div class="feature-box">
            <div class="feature-icon">🛣️</div>
            <h3>Roadside Boarding Points</h3>
            <p>Pick up along your route (Nairobi CBD, Westlands, Kangemi, Limuru) rather than being forced to travel to the central terminus.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/config/footer.php'; ?>
