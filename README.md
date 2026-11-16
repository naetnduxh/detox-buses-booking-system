# Detox Buses Online Booking System
**Course Alignment:** BIT 313 Web Application Development  
**Technology Stack:** PHP 8.x, MySQL / MariaDB (PDO), HTML5, CSS3, Vanilla JavaScript  
**Default Access URL:** [http://localhost/detox_buses/](http://localhost/detox_buses/)

---

## 1. Project Overview & Problem Solved
Traditional intercity bus booking forces travelers to visit physical booking offices, stand in long queues, and forfeit their entire fare if an unexpected emergency prevents them from traveling.

The **Detox Buses Online Booking System** is an intercity passenger reservation and fleet management platform operating routes from Nairobi to major counties (such as Kakamega, Kisumu, Nakuru, Eldoret, and Mombasa).

### Key Upgrades:
1. **1-Hour Pre-Departure Self-Service Cash-Out:** Passengers can cancel their tickets online up to 60 minutes before departure directly from their dashboard.
2. **Automated 10% Cancellation Fee Rule:** The system deducts a 10% cancellation fee and immediately refunds 90% via simulated M-Pesa reversal. The cancelled seat instantly turns green on the public bus map.
3. **Smart Group Seat Finder:** A 1-click JavaScript algorithm automatically finds and highlights adjacent, side-by-side open seats for families or traveling groups.
4. **Flexible Roadside Boarding Points:** Passengers choose their preferred pickup stage along the highway (e.g., Nairobi CBD Office, Westlands, Kangemi, Limuru) rather than being restricted to the terminus.

---

## 2. Default Credentials

| Role | Email Address | Password | Capabilities |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@detoxbuses.co.ke` | `Admin123!` | Protected Dashboard, Fleet CRUD, Route Scheduling CRUD, Live Manifests, Refund Audit Log |
| **Passenger** | `passenger@example.com` | `User123!` | Schedule search, Visual 2x2 seat selection, Group finder, M-Pesa checkout, E-Ticket printing, 1-Hour Cash-Out |

*(Note: Fast 1-click auto-fill buttons are also available directly on the login page for rapid testing and grading).*

---

## 3. Database Architecture (5 Relational Tables)
The database `detox_buses_db` includes 5 relational tables:

1. `users`: Stores user identity, contact details, bcrypt password hashes, and session roles (`customer`, `admin`).
2. `buses`: Fleet records including model name, registration plate number, seat capacity (32 seats), and class (`Standard`, `Executive`, `VIP`).
3. `routes`: Scheduled intercity trips connecting a bus to an origin, destination, roadside boarding points, travel date, departure time, and fare.
4. `bookings`: Confirmed reservations tracking booking reference (`DTX-2026-XXXX`), user, route, assigned seat number, boarding point, fare, M-Pesa code, and status (`Confirmed`, `Refunded`).
5. `refunds`: Financial audit log for all self-service cancellations recording original fare, 10% fee retained, 90% refund reversed, phone number, and reversal reference (`REV-2026-XXXX`).

---

## 4. Folder Structure (XAMPP / VS Code)

```text
detox_buses/
├── config/
│   ├── db.php             # PDO connection (auto-ports 3307/3306), Kenya timezone (Africa/Nairobi), auth helpers
│   ├── header.php         # Reusable navigation header and flash alerts
│   └── footer.php         # Reusable footer and script tags
├── auth/
│   ├── login.php          # Session authentication with demo auto-fill
│   ├── register.php       # Customer registration with password hashing
│   └── logout.php         # Clean session termination
├── admin/
│   ├── dashboard.php      # Overview stats (Net revenue, fleet size, active bookings, recent audits)
│   ├── manage_buses.php   # Fleet CRUD (Create, Read, Update, Delete buses)
│   ├── manage_routes.php  # Trip Schedule CRUD (Assign buses, routes, dates, times, fares)
│   ├── manifest.php       # Live passenger manifest with printable driver sheet
│   └── view_refunds.php   # 10% Cancellation fee & 90% refund audit log
├── passenger/
│   ├── seat_map.php       # 2x2 Visual Bus Map, Group Seat Finder, roadside pickup selector
│   ├── checkout.php       # Simulated M-Pesa payment and booking confirmation
│   ├── ticket.php         # Printable digital E-Ticket with QR/barcode preview
│   ├── my_bookings.php    # Customer dashboard with real-time cash-out eligibility countdown
│   └── cashout.php        # 1-Hour pre-departure cash-out processing engine
├── assets/
│   ├── css/style.css      # Responsive transport styling, bus map layout, print media rules
│   └── js/script.js       # Dynamic seat selection, group search algorithm, live fee breakdown
├── database.sql           # Raw SQL schema export
├── setup_database.php     # One-click automated database setup and data seeder
└── README.md              # Project documentation
```

---

## 5. How to Run and Test the System
1. Open **XAMPP Control Panel** and ensure **Apache** and **MySQL** are running.
2. In your web browser, navigate to:
   - Homepage: `http://localhost/detox_buses/`
   - Database Setup (if re-seeding): `http://localhost/detox_buses/setup_database.php`
3. **Test Passenger Flow:**
   - Search for trips to Kakamega, Kisumu, Nakuru, Eldoret, or Mombasa.
   - Click **Select Seats** to open the interactive 2x2 visual seat map.
   - Use the **Smart Group Seat Finder** to locate adjacent seats.
   - Pick your roadside boarding stage (e.g. Westlands).
   - Checkout with simulated M-Pesa (click "Auto-Generate Code" or type one).
   - View and print your **Detox Buses E-Ticket**.
4. **Test 1-Hour Cash-Out Engine:**
   - Go to **My Bookings & Cash-Out**.
   - If the trip departs in more than 60 minutes, click **Cash Out (90%)**.
   - Review the modal showing the 10% cancellation fee deduction and 90% instant refund.
   - Confirm: The seat is immediately released back to the visual seat map and recorded in the audit trail.
5. **Test Admin Flow:**
   - Log in as `admin@detoxbuses.co.ke` (`Admin123!`).
   - Visit the Admin Dashboard, add new buses to the fleet, schedule routes, view live passenger manifests, and inspect the refund audit logs.
