<?php
/**
 * Database Initializer & Seeder for Detox Buses
 * Run via CLI or browser: http://localhost/detox_buses/setup_database.php
 */

require_once __DIR__ . '/config/db.php';

try {
    $pdo = get_db_connection();

    // 1. Run schema file
    $sql_schema = file_get_contents(__DIR__ . '/database.sql');
    $pdo->exec($sql_schema);

    // 2. Clear old data or check if users exist
    $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Seed users
    if ($user_count == 0) {
        $admin_pass = password_hash('Admin123!', PASSWORD_BCRYPT);
        $customer_pass = password_hash('User123!', PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Detox Fleet Manager', 'admin@detoxbuses.co.ke', '+254712345678', $admin_pass, 'admin']);
        $stmt->execute(['Brian Omondi', 'passenger@example.com', '+254798765432', $customer_pass, 'customer']);
        $stmt->execute(['Faith Chebet', 'faith@example.com', '+254722114455', $customer_pass, 'customer']);
        echo "<p style='color:green;'>Users seeded successfully.</p>\n";
    }

    // Seed buses
    $bus_count = $pdo->query("SELECT COUNT(*) FROM buses")->fetchColumn();
    if ($bus_count == 0) {
        $buses = [
            ['Detox Executive', 'KDG 412X', 32, 'Executive'],
            ['Detox VIP Royal', 'KDH 889P', 32, 'VIP'],
            ['Detox Cruiser', 'KDJ 104M', 32, 'Standard'],
            ['Detox Express', 'KDK 550Z', 32, 'Executive'],
            ['Detox Platinum', 'KDL 302A', 32, 'VIP']
        ];
        $stmt = $pdo->prepare("INSERT INTO buses (bus_name, plate_number, total_seats, bus_type) VALUES (?, ?, ?, ?)");
        foreach ($buses as $bus) {
            $stmt->execute($bus);
        }
        echo "<p style='color:green;'>Buses seeded successfully.</p>\n";
    }

    // Seed routes for today and upcoming days
    $route_count = $pdo->query("SELECT COUNT(*) FROM routes")->fetchColumn();
    if ($route_count == 0) {
        $bus_ids = $pdo->query("SELECT bus_id FROM buses ORDER BY bus_id ASC")->fetchAll(PDO::FETCH_COLUMN);

        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $day_after = date('Y-m-d', strtotime('+2 days'));

        $sample_routes = [
            // Today trips
            [$bus_ids[0], 'Nairobi', 'Kakamega', 'Nairobi CBD Office, Westlands, Kangemi, Uthiru, Kikuyu', $today, '14:30:00', 1600.00],
            [$bus_ids[1], 'Nairobi', 'Kisumu', 'Nairobi CBD Office, Westlands, Kangemi, Naivasha Junction, Nakuru', $today, '20:00:00', 1800.00],
            [$bus_ids[2], 'Nairobi', 'Mombasa', 'Nairobi CBD Office, South C/Bellevue, Cabanas, Mlolongo, Athi River', $today, '21:30:00', 2000.00],
            
            // Tomorrow trips
            [$bus_ids[0], 'Nairobi', 'Kakamega', 'Nairobi CBD Office, Westlands, Kangemi, Uthiru, Kikuyu', $tomorrow, '07:30:00', 1500.00],
            [$bus_ids[3], 'Nairobi', 'Nakuru', 'Nairobi CBD Office, Westlands, Kangemi, Kinungi', $tomorrow, '09:00:00', 900.00],
            [$bus_ids[1], 'Nairobi', 'Eldoret', 'Nairobi CBD Office, Westlands, Kangemi, Naivasha', $tomorrow, '10:30:00', 1400.00],
            [$bus_ids[4], 'Nairobi', 'Kisumu', 'Nairobi CBD Office, Westlands, Kangemi, Naivasha, Nakuru', $tomorrow, '19:30:00', 1800.00],
            [$bus_ids[2], 'Nairobi', 'Mombasa', 'Nairobi CBD Office, South C/Bellevue, Cabanas, Mlolongo', $tomorrow, '21:00:00', 2200.00],

            // Day after tomorrow
            [$bus_ids[0], 'Nairobi', 'Kakamega', 'Nairobi CBD Office, Westlands, Kangemi, Kikuyu', $day_after, '08:00:00', 1500.00],
            [$bus_ids[1], 'Nairobi', 'Kisumu', 'Nairobi CBD Office, Westlands, Kangemi', $day_after, '14:00:00', 1700.00],
            [$bus_ids[3], 'Nairobi', 'Eldoret', 'Nairobi CBD Office, Westlands, Kangemi', $day_after, '15:30:00', 1400.00]
        ];

        $stmt = $pdo->prepare("INSERT INTO routes (bus_id, origin, destination, boarding_points, travel_date, departure_time, fare) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($sample_routes as $r) {
            $stmt->execute($r);
        }
        echo "<p style='color:green;'>Routes seeded successfully.</p>\n";
    }

    // Seed sample bookings & a sample refund audit
    $booking_count = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    if ($booking_count == 0) {
        $user_ids = $pdo->query("SELECT user_id FROM users WHERE role='customer' ORDER BY user_id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $route_ids = $pdo->query("SELECT route_id, fare FROM routes ORDER BY route_id ASC")->fetchAll();

        if (!empty($user_ids) && !empty($route_ids)) {
            $u1 = $user_ids[0];
            $u2 = isset($user_ids[1]) ? $user_ids[1] : $user_ids[0];
            $r1 = $route_ids[0]; // Trip 1
            $r2 = isset($route_ids[3]) ? $route_ids[3] : $r1; // Trip 2

            // Active Confirmed bookings for Route 1 (Seats 5, 6, 12)
            $stmt = $pdo->prepare("INSERT INTO bookings (booking_ref, user_id, route_id, seat_number, boarding_point, amount_paid, mpesa_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['DTX-2026-4821', $u1, $r1['route_id'], 5, 'Westlands', $r1['fare'], 'QBC89218LK', 'Confirmed']);
            $stmt->execute(['DTX-2026-4822', $u1, $r1['route_id'], 6, 'Westlands', $r1['fare'], 'QBC89219LK', 'Confirmed']);
            $stmt->execute(['DTX-2026-3109', $u2, $r1['route_id'], 12, 'Kangemi', $r1['fare'], 'QDK77123MN', 'Confirmed']);

            // A trip for tomorrow
            $stmt->execute(['DTX-2026-9041', $u1, $r2['route_id'], 14, 'Nairobi CBD Office', $r2['fare'], 'QPL99201AB', 'Confirmed']);

            // One cancelled booking with 10% penalty and 90% refund
            $stmt->execute(['DTX-2026-1044', $u2, $r2['route_id'], 8, 'Westlands', $r2['fare'], 'QRM33019ZZ', 'Refunded']);
            $refunded_booking_id = $pdo->lastInsertId();

            $orig_fare = (float)$r2['fare'];
            $fee = round($orig_fare * 0.10, 2);
            $refund_amount = round($orig_fare * 0.90, 2);

            $stmt_ref = $pdo->prepare("INSERT INTO refunds (booking_id, original_amount, cancellation_fee, refund_amount, mpesa_phone, reversal_ref) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_ref->execute([$refunded_booking_id, $orig_fare, $fee, $refund_amount, '+254722114455', 'REV-2026-7731']);

            echo "<p style='color:green;'>Sample bookings and refund audit log seeded.</p>\n";
        }
    }

    echo "<h3>Detox Buses Database Setup Completed Successfully!</h3>";
    echo "<p>Admin Login: <strong>admin@detoxbuses.co.ke</strong> / Password: <strong>Admin123!</strong></p>";
    echo "<p>Passenger Login: <strong>passenger@example.com</strong> / Password: <strong>User123!</strong></p>";
    echo "<p><a href='index.php'>Go to Detox Buses Homepage</a> | <a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";

} catch (PDOException $e) {
    die("<h3 style='color:red;'>Setup Failed: " . htmlspecialchars($e->getMessage()) . "</h3>");
}
