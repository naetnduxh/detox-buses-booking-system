<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo = get_db_connection();

    $buses = $pdo->query("SELECT bus_id, bus_name FROM buses ORDER BY bus_id ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($buses)) {
        die("No buses found in database. Run setup_database.php first.\n");
    }

    $bus_ids = array_keys($buses);
    $b1 = $bus_ids[0] ?? 1; // Detox Executive
    $b2 = $bus_ids[1] ?? 2; // Detox VIP Royal
    $b3 = $bus_ids[2] ?? 3; // Detox Cruiser
    $b4 = $bus_ids[3] ?? 4; // Detox Express
    $b5 = $bus_ids[4] ?? 5; // Detox Platinum

    $templates = [
        // Destination, bus_id, time, fare, boarding_points
        ['Kakamega', $b1, '07:30:00', 1500.00, 'Nairobi CBD Office, Westlands, Kangemi, Uthiru, Kikuyu'],
        ['Kakamega', $b1, '14:30:00', 1600.00, 'Nairobi CBD Office, Westlands, Kangemi, Uthiru, Kikuyu'],
        ['Kakamega', $b4, '21:00:00', 1700.00, 'Nairobi CBD Office, Westlands, Kangemi, Kikuyu'],
        ['Kisumu', $b2, '08:00:00', 1800.00, 'Nairobi CBD Office, Westlands, Kangemi, Naivasha Junction, Nakuru'],
        ['Kisumu', $b2, '20:00:00', 1800.00, 'Nairobi CBD Office, Westlands, Kangemi, Naivasha Junction, Nakuru'],
        ['Mombasa', $b5, '08:30:00', 2000.00, 'Nairobi CBD Office, South C/Bellevue, Cabanas, Mlolongo, Athi River'],
        ['Mombasa', $b3, '21:30:00', 2200.00, 'Nairobi CBD Office, South C/Bellevue, Cabanas, Mlolongo, Athi River'],
        ['Nakuru', $b4, '09:00:00', 900.00, 'Nairobi CBD Office, Westlands, Kangemi, Kinungi'],
        ['Nakuru', $b4, '15:00:00', 950.00, 'Nairobi CBD Office, Westlands, Kangemi, Kinungi'],
        ['Eldoret', $b3, '10:30:00', 1400.00, 'Nairobi CBD Office, Westlands, Kangemi, Naivasha'],
        ['Eldoret', $b5, '16:00:00', 1500.00, 'Nairobi CBD Office, Westlands, Kangemi, Naivasha'],
    ];

    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM routes WHERE destination = ? AND travel_date = ? AND departure_time = ?");
    $insert_stmt = $pdo->prepare("INSERT INTO routes (bus_id, origin, destination, boarding_points, travel_date, departure_time, fare, status) 
                                 VALUES (?, 'Nairobi', ?, ?, ?, ?, ?, 'Active')");

    $added_count = 0;
    // Generate for next 35 days from today
    for ($i = 0; $i <= 35; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        foreach ($templates as $t) {
            $dest = $t[0];
            $bus_id = $t[1];
            $time = $t[2];
            $fare = $t[3];
            $boarding = $t[4];

            $check_stmt->execute([$dest, $date, $time]);
            if ($check_stmt->fetchColumn() == 0) {
                $insert_stmt->execute([$bus_id, $dest, $boarding, $date, $time, $fare]);
                $added_count++;
            }
        }
    }

    echo "Successfully generated $added_count new trip schedules across the next 35 days!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
