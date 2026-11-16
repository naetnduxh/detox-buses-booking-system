<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$page_title = "Manage Schedules & Routes";
$error = '';
$edit_route = null;


if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    
    
    $chk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE route_id = ? AND status = 'Confirmed'");
    $chk->execute([$del_id]);
    $confirmed_count = $chk->fetchColumn();

    if ($confirmed_count > 0) {
        set_flash('error', "Cannot delete this schedule: It has {$confirmed_count} active confirmed passenger booking(s). You may set its status to Cancelled instead.");
    } else {
        $del = $pdo->prepare("DELETE FROM routes WHERE route_id = ?");
        if ($del->execute([$del_id])) {
            set_flash('success', 'Trip schedule deleted successfully.');
        } else {
            set_flash('error', 'Failed to delete schedule.');
        }
    }
    header('Location: /detox_buses/admin/manage_routes.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_id = isset($_POST['route_id']) ? (int)$_POST['route_id'] : 0;
    $bus_id = (int)($_POST['bus_id'] ?? 0);
    $origin = trim($_POST['origin'] ?? 'Nairobi');
    $destination = trim($_POST['destination'] ?? '');
    $boarding_points = trim($_POST['boarding_points'] ?? '');
    $travel_date = trim($_POST['travel_date'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? '');
    $fare = (float)($_POST['fare'] ?? 0);
    $status = trim($_POST['status'] ?? 'Active');

    if ($bus_id <= 0 || empty($destination) || empty($boarding_points) || empty($travel_date) || empty($departure_time) || $fare <= 0) {
        $error = "All schedule fields including a valid bus, destination, pickup stages, date, time, and fare are required.";
    } else {
        if ($route_id > 0) {
            // Update
            $stmt = $pdo->prepare("UPDATE routes SET bus_id = ?, origin = ?, destination = ?, boarding_points = ?, travel_date = ?, departure_time = ?, fare = ?, status = ? WHERE route_id = ?");
            if ($stmt->execute([$bus_id, $origin, $destination, $boarding_points, $travel_date, $departure_time, $fare, $status, $route_id])) {
                set_flash('success', 'Trip schedule updated successfully.');
                header('Location: /detox_buses/admin/manage_routes.php');
                exit;
            } else {
                $error = "Failed to update trip schedule.";
            }
        } else {
            // Create
            $stmt = $pdo->prepare("INSERT INTO routes (bus_id, origin, destination, boarding_points, travel_date, departure_time, fare, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$bus_id, $origin, $destination, $boarding_points, $travel_date, $departure_time, $fare, $status])) {
                set_flash('success', 'New trip schedule created successfully.');
                header('Location: /detox_buses/admin/manage_routes.php');
                exit;
            } else {
                $error = "Failed to save trip schedule.";
            }
        }
    }
}


if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM routes WHERE route_id = ?");
    $stmt->execute([$edit_id]);
    $edit_route = $stmt->fetch();
}


$buses = $pdo->query("SELECT bus_id, bus_name, plate_number, bus_type FROM buses ORDER BY bus_name ASC")->fetchAll();


$routes = $pdo->query("SELECT r.*, b.bus_name, b.plate_number, b.total_seats,
                       (SELECT COUNT(*) FROM bookings bk WHERE bk.route_id = r.route_id AND bk.status = 'Confirmed') AS booked_seats
                       FROM routes r
                       JOIN buses b ON r.bus_id = b.bus_id
                       ORDER BY r.travel_date DESC, r.departure_time ASC")->fetchAll();

require_once __DIR__ . '/../config/header.php';
?>

<div class="card-header">
    <div>
        <h2>Route & Daily Trip Scheduling</h2>
        <p style="color:var(--slate); font-size:0.9rem;">
            Assign buses, set destination routes, define roadside boarding stages, and specify fares.
        </p>
    </div>
    <a href="/detox_buses/admin/dashboard.php" style="color:var(--slate); text-decoration:none; font-weight:700;">
        ← Back to Dashboard
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-error">
        <span class="alert-icon">⚠</span>
        <span><?= htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 360px 1fr; gap: 2rem; align-items: start;">
    
    <!-- Add / Edit Schedule Form -->
    <div class="card">
        <div class="card-header">
            <h3><?= $edit_route ? 'Edit Trip Schedule' : 'Schedule New Trip'; ?></h3>
        </div>

        <form method="POST" action="">
            <?php if ($edit_route): ?>
                <input type="hidden" name="route_id" value="<?= $edit_route['route_id']; ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="bus_id">Assigned Bus Fleet</label>
                <select id="bus_id" name="bus_id" class="form-control" required>
                    <option value="">-- Choose Bus --</option>
                    <?php foreach ($buses as $bus): ?>
                        <option value="<?= $bus['bus_id']; ?>" <?= ($edit_route && $edit_route['bus_id'] == $bus['bus_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($bus['bus_name']); ?> (<?= htmlspecialchars($bus['plate_number']); ?>) - <?= htmlspecialchars($bus['bus_type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="origin">Departure Origin</label>
                <input type="text" id="origin" name="origin" class="form-control" value="<?= htmlspecialchars($edit_route['origin'] ?? 'Nairobi'); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="destination">Destination County</label>
                <select id="destination" name="destination" class="form-control" required>
                    <option value="">-- Select Destination --</option>
                    <?php 
                        $dests = ['Kakamega', 'Kisumu', 'Nakuru', 'Eldoret', 'Mombasa', 'Kitale', 'Busia', 'Malindi'];
                        foreach ($dests as $d):
                    ?>
                        <option value="<?= $d; ?>" <?= ($edit_route && $edit_route['destination'] === $d) ? 'selected' : ''; ?>><?= $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="boarding_points">Roadside Boarding Stages</label>
                <textarea id="boarding_points" name="boarding_points" class="form-control" rows="2" placeholder="e.g. Nairobi CBD Office, Westlands, Kangemi, Kikuyu" required><?= htmlspecialchars($edit_route['boarding_points'] ?? 'Nairobi CBD Office, Westlands, Kangemi'); ?></textarea>
                <small style="color:var(--slate); font-size:0.75rem;">Comma-separated list of pickup stages along highway.</small>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="travel_date">Travel Date</label>
                    <input type="date" id="travel_date" name="travel_date" class="form-control" value="<?= htmlspecialchars($edit_route['travel_date'] ?? date('Y-m-d')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="departure_time">Departure Time</label>
                    <input type="time" id="departure_time" name="departure_time" class="form-control" value="<?= htmlspecialchars($edit_route['departure_time'] ?? '08:00'); ?>" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="fare">Fare (KES)</label>
                    <input type="number" id="fare" name="fare" class="form-control" step="50" min="100" value="<?= htmlspecialchars($edit_route['fare'] ?? '1500'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Trip Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="Active" <?= ($edit_route['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Completed" <?= ($edit_route['status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?= ($edit_route['status'] ?? '') === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;">
                    <?= $edit_route ? 'Update Schedule' : 'Create Schedule'; ?>
                </button>
                <?php if ($edit_route): ?>
                    <a href="/detox_buses/admin/manage_routes.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Schedules List -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.2rem; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 1.15rem;">Active & Upcoming Trip Schedules (<?= count($routes); ?>)</h3>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Trip & Bus</th>
                        <th>Date & Time</th>
                        <th>Bookings</th>
                        <th>Fare</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($routes)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--slate);">No routes scheduled yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($routes as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($r['origin']); ?> ➔ <?= htmlspecialchars($r['destination']); ?></strong><br>
                                    <small style="color:var(--slate);"><?= htmlspecialchars($r['bus_name']); ?> (<?= htmlspecialchars($r['plate_number']); ?>)</small><br>
                                    <small style="color:#b45309;">📍 <?= htmlspecialchars($r['boarding_points']); ?></small>
                                </td>
                                <td>
                                    <div><?= format_date($r['travel_date']); ?></div>
                                    <strong style="color:var(--primary);"><?= format_time($r['departure_time']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= $r['booked_seats']; ?> / <?= $r['total_seats']; ?> Booked
                                    </span>
                                </td>
                                <td><strong><?= format_kes($r['fare']); ?></strong></td>
                                <td>
                                    <?php if ($r['status'] === 'Active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php elseif ($r['status'] === 'Cancelled'): ?>
                                        <span class="badge badge-danger">Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><?= htmlspecialchars($r['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:0.3rem;">
                                        <a href="/detox_buses/admin/manifest.php?route_id=<?= $r['route_id']; ?>" class="btn btn-secondary btn-sm" style="background:#e0f2fe; color:#0369a1;">
                                            📋 Manifest
                                        </a>
                                        <div style="display:flex; gap:0.3rem;">
                                            <a href="/detox_buses/admin/manage_routes.php?edit=<?= $r['route_id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                            <a href="/detox_buses/admin/manage_routes.php?delete=<?= $r['route_id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this trip schedule?');">Delete</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
