<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$page_title = "Manage Fleet Buses";
$error = '';
$edit_bus = null;

// Handle Delete Bus
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    
    // Check if bus has active routes
    $chk = $pdo->prepare("SELECT COUNT(*) FROM routes WHERE bus_id = ?");
    $chk->execute([$del_id]);
    if ($chk->fetchColumn() > 0) {
        set_flash('error', 'Cannot delete this bus because it is assigned to existing scheduled trips.');
    } else {
        $del = $pdo->prepare("DELETE FROM buses WHERE bus_id = ?");
        if ($del->execute([$del_id])) {
            set_flash('success', 'Bus deleted from fleet successfully.');
        } else {
            set_flash('error', 'Failed to delete bus.');
        }
    }
    header('Location: /detox_buses/admin/manage_buses.php');
    exit;
}

// Handle Add / Edit Bus POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bus_id = isset($_POST['bus_id']) ? (int)$_POST['bus_id'] : 0;
    $bus_name = trim($_POST['bus_name'] ?? '');
    $plate_number = strtoupper(trim($_POST['plate_number'] ?? ''));
    $total_seats = isset($_POST['total_seats']) ? (int)$_POST['total_seats'] : 32;
    $bus_type = trim($_POST['bus_type'] ?? 'Executive');

    if (empty($bus_name) || empty($plate_number)) {
        $error = "Bus name and registration plate number are required.";
    } else {
        if ($bus_id > 0) {
            // Update
            $stmt = $pdo->prepare("UPDATE buses SET bus_name = ?, plate_number = ?, total_seats = ?, bus_type = ? WHERE bus_id = ?");
            if ($stmt->execute([$bus_name, $plate_number, $total_seats, $bus_type, $bus_id])) {
                set_flash('success', 'Bus fleet details updated successfully.');
                header('Location: /detox_buses/admin/manage_buses.php');
                exit;
            } else {
                $error = "Failed to update bus details.";
            }
        } else {
            // Create
            // Check plate duplicate
            $chk = $pdo->prepare("SELECT bus_id FROM buses WHERE plate_number = ?");
            $chk->execute([$plate_number]);
            if ($chk->fetch()) {
                $error = "A bus with plate number '{$plate_number}' is already registered in the fleet.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO buses (bus_name, plate_number, total_seats, bus_type) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$bus_name, $plate_number, $total_seats, $bus_type])) {
                    set_flash('success', 'New bus added to Detox fleet successfully.');
                    header('Location: /detox_buses/admin/manage_buses.php');
                    exit;
                } else {
                    $error = "Failed to add bus to database.";
                }
            }
        }
    }
}

// Check if Editing
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM buses WHERE bus_id = ?");
    $stmt->execute([$edit_id]);
    $edit_bus = $stmt->fetch();
}

// Fetch all buses with active trip counts
$buses = $pdo->query("SELECT b.*, 
                      (SELECT COUNT(*) FROM routes r WHERE r.bus_id = b.bus_id) AS total_trips
                      FROM buses b ORDER BY b.bus_id DESC")->fetchAll();

require_once __DIR__ . '/../config/header.php';
?>

<div class="card-header">
    <div>
        <h2>Fleet Management (Buses)</h2>
        <p style="color:var(--slate); font-size:0.9rem;">
            Register, update, or remove buses in the Detox intercity fleet.
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

<div style="display: grid; grid-template-columns: 340px 1fr; gap: 2rem; align-items: start;">
    
    <!-- Add / Edit Bus Form -->
    <div class="card">
        <div class="card-header">
            <h3><?= $edit_bus ? 'Edit Fleet Bus' : 'Add New Bus'; ?></h3>
        </div>

        <form method="POST" action="">
            <?php if ($edit_bus): ?>
                <input type="hidden" name="bus_id" value="<?= $edit_bus['bus_id']; ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="bus_name">Bus Model / Name</label>
                <input type="text" id="bus_name" name="bus_name" class="form-control" placeholder="e.g. Detox VIP Royal" required value="<?= htmlspecialchars($edit_bus['bus_name'] ?? ''); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="plate_number">Plate Number</label>
                <input type="text" id="plate_number" name="plate_number" class="form-control" placeholder="e.g. KDG 412X" required value="<?= htmlspecialchars($edit_bus['plate_number'] ?? ''); ?>" style="text-transform: uppercase;">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="total_seats">Total Seats (Capacity)</label>
                <input type="number" id="total_seats" name="total_seats" class="form-control" value="<?= htmlspecialchars($edit_bus['total_seats'] ?? '32'); ?>" min="16" max="60" required>
                <small style="color:var(--slate); font-size:0.75rem;">Standard Detox 2x2 layout is 32 seats.</small>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="bus_type">Bus Class / Type</label>
                <select id="bus_type" name="bus_type" class="form-control">
                    <option value="Executive" <?= ($edit_bus['bus_type'] ?? '') === 'Executive' ? 'selected' : ''; ?>>Executive</option>
                    <option value="VIP" <?= ($edit_bus['bus_type'] ?? '') === 'VIP' ? 'selected' : ''; ?>>VIP</option>
                    <option value="Standard" <?= ($edit_bus['bus_type'] ?? '') === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                </select>
            </div>

            <div style="display:flex; gap:0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;">
                    <?= $edit_bus ? 'Update Bus' : 'Register Bus'; ?>
                </button>
                <?php if ($edit_bus): ?>
                    <a href="/detox_buses/admin/manage_buses.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Bus Fleet List -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.2rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.15rem;">Registered Fleet (<?= count($buses); ?> Buses)</h3>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bus Name</th>
                        <th>Plate No</th>
                        <th>Seats</th>
                        <th>Type</th>
                        <th>Trips</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($buses)): ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--slate);">No buses found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($buses as $b): ?>
                            <tr>
                                <td>#<?= $b['bus_id']; ?></td>
                                <td><strong><?= htmlspecialchars($b['bus_name']); ?></strong></td>
                                <td><span style="font-family:monospace; font-weight:700;"><?= htmlspecialchars($b['plate_number']); ?></span></td>
                                <td><?= $b['total_seats']; ?> Seats</td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($b['bus_type']); ?></span></td>
                                <td><span class="badge badge-success"><?= $b['total_trips']; ?> Scheduled</span></td>
                                <td>
                                    <div style="display:flex; gap:0.4rem;">
                                        <a href="/detox_buses/admin/manage_buses.php?edit=<?= $b['bus_id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <a href="/detox_buses/admin/manage_buses.php?delete=<?= $b['bus_id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this bus?');">Delete</a>
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
