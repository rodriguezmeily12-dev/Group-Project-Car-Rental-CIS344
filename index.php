<?php
/**
 * Dashboard (home page)
 * Shows today's numbers, the cars that are out now, and upcoming pickups.
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

// Fleet size and how many cars are out on an Active rental right now
$fleet = $conn->query("
    SELECT (SELECT COUNT(*) FROM cars) AS total_cars,
           (SELECT COUNT(DISTINCT car_id) FROM rentals WHERE status = 'Active') AS cars_out
")->fetch_assoc();

$cars_available = $fleet["total_cars"] - $fleet["cars_out"];

// Total money still owed, using the joined rental details (cost minus payments)
$owed = $conn->query("
    SELECT COALESCE(SUM(GREATEST(d.balance, 0)), 0) AS total_owed
    FROM (" . RENTAL_DETAILS_SQL . ") AS d
")->fetch_assoc()["total_owed"];

$out_now  = fetch_rentals($conn, "Active", "return_soonest");
$upcoming = fetch_rentals($conn, "Booked", "pickup_soonest");

$page_title  = "Dashboard";
$active_page = "index.php";
require __DIR__ . "/includes/header.php";
?>

<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Today is <?= e(date("l, F j, Y")) ?>.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="add_rental.php">Add rental</a>
        <a class="btn btn-secondary" href="add_customer.php">Add customer</a>
        <a class="btn btn-secondary" href="add_payment.php">Record payment</a>
    </div>
</div>

<section class="figures" aria-label="Today at a glance">
    <div class="figure">
        <strong><?= (int) $cars_available ?> of <?= (int) $fleet["total_cars"] ?></strong>
        <span>Cars available now</span>
    </div>
    <div class="figure">
        <strong><?= count($out_now) ?></strong>
        <span>Out on rental</span>
    </div>
    <div class="figure">
        <strong><?= count($upcoming) ?></strong>
        <span>Upcoming pickups</span>
    </div>
    <div class="figure<?= $owed > 0 ? " is-owed" : "" ?>">
        <strong><?= e(money($owed)) ?></strong>
        <span>Balance owed</span>
    </div>
</section>

<div class="section-title">
    <h2>Out now</h2>
    <a href="view_rentals.php?status=Active">All active rentals</a>
</div>
<div class="table-wrap">
    <table class="stack">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Car</th>
                <th>Picked up</th>
                <th>Due back</th>
                <th>Status</th>
                <th class="num">Balance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($out_now) === 0): ?>
            <tr class="empty-row"><td colspan="7">No cars are out right now.</td></tr>
        <?php endif; ?>
        <?php foreach ($out_now as $r): ?>
            <tr>
                <td data-label="Customer"><?= e($r["first_name"] . " " . $r["last_name"]) ?></td>
                <td data-label="Car"><?= e($r["brand"] . " " . $r["model"]) ?></td>
                <td data-label="Picked up"><?= e(nice_date($r["rental_date"])) ?></td>
                <td data-label="Due back"><?= e(nice_date($r["return_date"])) ?></td>
                <td data-label="Status"><?= rental_status($r) ?></td>
                <td data-label="Balance" class="num<?= $r["balance"] > 0 ? " owed" : "" ?>"><?= e(money($r["balance"])) ?></td>
                <td data-label="Actions"><?= rental_actions($r, "index.php") ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="section-title">
    <h2>Upcoming pickups</h2>
    <a href="view_rentals.php?status=Booked">All bookings</a>
</div>
<div class="table-wrap">
    <table class="stack">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Car</th>
                <th>Pickup</th>
                <th>Return</th>
                <th class="num">Total</th>
                <th class="num">Balance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($upcoming) === 0): ?>
            <tr class="empty-row"><td colspan="7">No upcoming pickups. <a href="add_rental.php">Add a rental</a></td></tr>
        <?php endif; ?>
        <?php foreach ($upcoming as $r): ?>
            <tr>
                <td data-label="Customer"><?= e($r["first_name"] . " " . $r["last_name"]) ?></td>
                <td data-label="Car"><?= e($r["brand"] . " " . $r["model"]) ?></td>
                <td data-label="Pickup" class="nowrap"><?= e(nice_date($r["rental_date"])) ?></td>
                <td data-label="Return" class="nowrap"><?= e(nice_date($r["return_date"])) ?></td>
                <td data-label="Total" class="num"><?= e(money($r["total_cost"])) ?></td>
                <td data-label="Balance" class="num<?= $r["balance"] > 0 ? " owed" : "" ?>"><?= e(money($r["balance"])) ?></td>
                <td data-label="Actions"><?= rental_actions($r, "index.php") ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
