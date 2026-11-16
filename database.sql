
CREATE DATABASE IF NOT EXISTS `detox_buses_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `detox_buses_db`;


CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `buses` (
  `bus_id` INT AUTO_INCREMENT PRIMARY KEY,
  `bus_name` VARCHAR(100) NOT NULL,
  `plate_number` VARCHAR(20) NOT NULL UNIQUE,
  `total_seats` INT NOT NULL DEFAULT 32,
  `bus_type` ENUM('Standard', 'Executive', 'VIP') NOT NULL DEFAULT 'Executive',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `routes` (
  `route_id` INT AUTO_INCREMENT PRIMARY KEY,
  `bus_id` INT NOT NULL,
  `origin` VARCHAR(100) NOT NULL DEFAULT 'Nairobi',
  `destination` VARCHAR(100) NOT NULL,
  `boarding_points` TEXT NOT NULL,
  `travel_date` DATE NOT NULL,
  `departure_time` TIME NOT NULL,
  `fare` DECIMAL(10,2) NOT NULL,
  `status` ENUM('Active', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_routes_buses` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_ref` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `route_id` INT NOT NULL,
  `seat_number` INT NOT NULL,
  `boarding_point` VARCHAR(150) NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `mpesa_code` VARCHAR(50) NOT NULL,
  `status` ENUM('Confirmed', 'Refunded') NOT NULL DEFAULT 'Confirmed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_bookings_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookings_routes` FOREIGN KEY (`route_id`) REFERENCES `routes` (`route_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `refunds` (
  `refund_id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `original_amount` DECIMAL(10,2) NOT NULL,
  `cancellation_fee` DECIMAL(10,2) NOT NULL,
  `refund_amount` DECIMAL(10,2) NOT NULL,
  `mpesa_phone` VARCHAR(20) NOT NULL,
  `reversal_ref` VARCHAR(50) NOT NULL UNIQUE,
  `refunded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_refunds_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
