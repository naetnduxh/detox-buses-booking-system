<?php
require_once __DIR__ . '/../config/db.php';

$page_title = "Create Passenger Account";
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already registered
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "An account with this email address already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')");
            if ($insert->execute([$full_name, $email, $phone, $hashed])) {
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;
                $_SESSION['user_role'] = 'customer';

                set_flash('success', "Account created successfully! Welcome to Detox Buses.");
                header("Location: /detox_buses/index.php");
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

require_once __DIR__ . '/../config/header.php';
?>

<div style="max-width: 500px; margin: 2rem auto;">
    <div class="card">
        <div class="card-header" style="justify-content: center; text-align: center;">
            <h2>Passenger Registration</h2>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠</span>
                <span><?= htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g., Samuel Kibet" required value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="e.g., samuel@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="phone">M-Pesa Phone Number</label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="e.g., +254712345678" required value="<?= htmlspecialchars($_POST['phone'] ?? '+2547'); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="password">Password (minimum 6 characters)</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Create a strong password" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--slate);">
            Already have an account? <a href="/detox_buses/auth/login.php" style="color: var(--primary); font-weight: 700;">Sign in here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
