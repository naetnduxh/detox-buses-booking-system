<?php
require_once __DIR__ . '/../config/db.php';

$route_id = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;

if ($route_id <= 0) {
    set_flash('error', 'Please select a valid trip schedule.');
    header('Location: /detox_buses/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT r.*, b.bus_name, b.plate_number, b.bus_type, b.total_seats
                       FROM routes r
                       JOIN buses b ON r.bus_id = b.bus_id
                       WHERE r.route_id = ? AND r.status = 'Active' LIMIT 1");
$stmt->execute([$route_id]);
$route = $stmt->fetch();

if (!$route) {
    set_flash('error', 'The selected trip is no longer active or could not be found.');
    header('Location: /detox_buses/index.php');
    exit;
}

$stmt_seats = $pdo->prepare("SELECT seat_number FROM bookings WHERE route_id = ? AND status = 'Confirmed'");
$stmt_seats->execute([$route_id]);
$occupied_seats = $stmt_seats->fetchAll(PDO::FETCH_COLUMN);

$boarding_points_array = array_map('trim', explode(',', $route['boarding_points']));

$page_title = "Select Seats - " . $route['bus_name'] . " (" . $route['destination'] . ")";
require_once __DIR__ . '/../config/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <a href="/detox_buses/index.php" style="color: var(--slate); text-decoration: none; font-weight: 700;">
            ← Back to Trip Schedules
        </a>
        <h1 style="font-size: 1.7rem; font-weight: 800; margin-top: 0.3rem;">
            <?= htmlspecialchars($route['origin']); ?> ➔ <?= htmlspecialchars($route['destination']); ?>
        </h1>
        <p style="color: var(--slate); font-size: 0.95rem;">
            <?= htmlspecialchars($route['bus_name']); ?> (<?= htmlspecialchars($route['plate_number']); ?>) &bull; 
            <?= format_date($route['travel_date']); ?> at <?= format_time($route['departure_time']); ?>
        </p>
    </div>
    <div style="text-align: right;">
        <span style="font-size: 0.85rem; color: var(--slate); text-transform: uppercase; font-weight: 700;">Ticket Fare</span><br>
        <span style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">
            <?= format_kes($route['fare']); ?>
        </span>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 340px; gap: 2rem; align-items: start;">
    
    <!-- Left Column: Bus Map & Group Finder -->
    <div class="card" style="padding: 1.5rem;">
        <div class="card-header">
            <h3>Visual 2x2 Bus Seat Layout</h3>
            <span style="font-size: 0.85rem; color: var(--slate);">
                <?= count($occupied_seats); ?> / <?= $route['total_seats']; ?> Seats Booked
            </span>
        </div>

        <!-- Smart Group Seat Finder -->
        <div class="group-finder-card">
            <div>
                <strong>👥 Smart Group Seat Finder</strong>
                <p style="font-size: 0.85rem; color: var(--slate);">Traveling with others? Automatically find adjacent seats.</p>
                <div id="group_finder_status" style="font-size: 0.85rem; margin-top: 4px;"></div>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <select id="group_size_select" class="form-control" style="width: auto; padding: 0.4rem 0.6rem;">
                    <option value="2">2 Adjacent</option>
                    <option value="3">3 in a Row</option>
                    <option value="4">Full 4-Seat Row</option>
                </select>
                <button type="button" id="btn_find_group" class="btn btn-secondary btn-sm">
                    Find Adjacent
                </button>
            </div>
        </div>

        <!-- Seat Map Legend -->
        <div class="seat-legend">
            <div class="legend-item">
                <div class="legend-box avail"></div>
                <span>Available (Clickable)</span>
            </div>
            <div class="legend-item">
                <div class="legend-box occ"></div>
                <span>Booked (Locked)</span>
            </div>
            <div class="legend-item">
                <div class="legend-box sel"></div>
                <span>Your Selection</span>
            </div>
        </div>

        <!-- The 2x2 Bus Visual Map Container -->
        <div class="bus-container">
            <!-- Front Cabin -->
            <div class="bus-front-cabin">
                <div class="steering-wheel">
                    <span>☸</span>
                    <span style="font-size:0.85rem;">Driver Cabin</span>
                </div>
                <div class="driver-seat">Front Windshield</div>
            </div>

            <!-- Seat Grid (8 Rows x 4 Seats = 32 Seats) -->
            <div class="seat-grid">
                <?php for ($row = 1; $row <= 8; $row++): ?>
                    <?php 
                        $seatA = ($row - 1) * 4 + 1;
                        $seatB = ($row - 1) * 4 + 2;
                        $seatC = ($row - 1) * 4 + 3;
                        $seatD = ($row - 1) * 4 + 4;

                        $isAOccupied = in_array($seatA, $occupied_seats);
                        $isBOccupied = in_array($seatB, $occupied_seats);
                        $isCOccupied = in_array($seatC, $occupied_seats);
                        $isDOccupied = in_array($seatD, $occupied_seats);
                    ?>

                    <!-- Left Window: Seat A -->
                    <div class="seat <?= $isAOccupied ? 'occupied' : 'available'; ?>" data-seat="<?= $seatA; ?>" title="Row <?= $row; ?> Window Seat <?= $seatA; ?>">
                        <span><?= $seatA; ?></span>
                        <small style="font-size:0.65rem;opacity:0.8;"><?= $isAOccupied ? '🔒' : 'A'; ?></small>
                    </div>

                    <!-- Left Aisle: Seat B -->
                    <div class="seat <?= $isBOccupied ? 'occupied' : 'available'; ?>" data-seat="<?= $seatB; ?>" title="Row <?= $row; ?> Aisle Seat <?= $seatB; ?>">
                        <span><?= $seatB; ?></span>
                        <small style="font-size:0.65rem;opacity:0.8;"><?= $isBOccupied ? '🔒' : 'B'; ?></small>
                    </div>

                    <!-- Center Aisle Divider -->
                    <div class="aisle-label">
                        R<?= $row; ?>
                    </div>

                    <!-- Right Aisle: Seat C -->
                    <div class="seat <?= $isCOccupied ? 'occupied' : 'available'; ?>" data-seat="<?= $seatC; ?>" title="Row <?= $row; ?> Aisle Seat <?= $seatC; ?>">
                        <span><?= $seatC; ?></span>
                        <small style="font-size:0.65rem;opacity:0.8;"><?= $isCOccupied ? '🔒' : 'C'; ?></small>
                    </div>

                    <!-- Right Window: Seat D -->
                    <div class="seat <?= $isDOccupied ? 'occupied' : 'available'; ?>" data-seat="<?= $seatD; ?>" title="Row <?= $row; ?> Window Seat <?= $seatD; ?>">
                        <span><?= $seatD; ?></span>
                        <small style="font-size:0.65rem;opacity:0.8;"><?= $isDOccupied ? '🔒' : 'D'; ?></small>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Selection Summary & Checkout -->
    <div class="card" style="position: sticky; top: 90px;">
        <div class="card-header">
            <h3>Trip Summary</h3>
        </div>

        <form method="POST" action="/detox_buses/passenger/checkout.php">
            <input type="hidden" name="route_id" value="<?= $route['route_id']; ?>">
            <input type="hidden" name="selected_seat" id="selected_seat" value="">

            <div style="background: #f8fafc; border-radius: 8px; padding: 1rem; margin-bottom: 1.2rem; border: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="color:var(--slate); font-size:0.9rem;">Destination:</span>
                    <strong><?= htmlspecialchars($route['destination']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="color:var(--slate); font-size:0.9rem;">Travel Date:</span>
                    <strong><?= format_date($route['travel_date']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="color:var(--slate); font-size:0.9rem;">Departure Time:</span>
                    <strong><?= format_time($route['departure_time']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border); padding-top: 0.5rem; margin-top: 0.5rem;">
                    <span style="color:var(--slate); font-size:0.9rem;">Chosen Seat:</span>
                    <strong id="display_selected_seat" style="color: var(--primary); font-size: 1.1rem;">
                        None selected
                    </strong>
                </div>
            </div>

            <!-- Roadside Boarding Stage Selection -->
            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label for="boarding_point">Flexible Pickup Stage</label>
                <select name="boarding_point" id="boarding_point" class="form-control" required>
                    <?php foreach ($boarding_points_array as $pt): ?>
                        <option value="<?= htmlspecialchars($pt); ?>"><?= htmlspecialchars($pt); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--slate); font-size: 0.75rem;">
                    Board along the highway at your preferred stage.
                </small>
            </div>

            <div style="margin-bottom: 1.5rem; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 0.8rem; font-size: 0.85rem; color: #065f46;">
                🛡️ <strong>Detox Guarantee:</strong> You can cash out this ticket online with a 90% instant refund up to 1 hour before departure.
            </div>

            <button type="submit" id="btn_proceed_checkout" class="btn btn-accent btn-disabled" disabled style="width: 100%;">
                Proceed to Checkout (<?= format_kes($route['fare']); ?>)
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
