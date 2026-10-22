<?php
/**
 * Cars page
 * Lists every car with its type, daily rate, and whether it's available.
 *
 * Availability is worked out from the rentals table instead of being
 * stored on the car, so it is always correct:
 *   INNER JOIN cartypes  -> type name and daily rate
 *   LEFT JOIN rentals    -> keeps cars that were never rented
 *   a car with an Active rental is out; otherwise it is available
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

$sql = "
    SELECT c.car_id, c.brand, c.model, c.year,
           t.type_name, t.daily_rate,
           COUNT(r.rental_id) AS rental_count,
           SUM(CASE WHEN r.status = 'Active' THEN 1 ELSE 0 END) AS active_rentals,
           MIN(CASE WHEN r.status = 'Booked' THEN r.rental_date END) AS next_booking
    FROM cars c
    INNER JOIN cartypes t ON c.type_id = t.type_id
    LEFT JOIN rentals r   ON r.car_id = c.car_id
    GROUP BY c.car_id, c.brand, c.model, c.year, t.type_name, t.daily_rate
    ORDER BY t.daily_rate, c.brand, c.model";

$cars = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$page_title  = "Cars";
$active_page = "view_cars.php";
require __DIR__ . "/includes/header.php";
?>

<div class="page-head">
    <div>
        <h1>Cars</h1>
        <p><?= count($cars) ?> cars in the fleet.</p>
    </div>
</div>

<div class="toolbar">
    <input type="search" placeholder="Search cars" aria-label="Search cars" data-table-search="#cars-table">
</div>

<div class="table-wrap">
    <table class="stack" id="cars-table">
        <thead>
            <tr>
                <th>Car</th>
                <th>Year</th>
                <th>Type</th>
                <th class="num">Daily rate</th>
                <th>Status</th>
                <th>Next booking</th>
                <th class="num">Times rented</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($cars) === 0): ?>
            <tr class="empty-row"><td colspan="8">No cars found.</td></tr>
        <?php endif; ?>
        <?php foreach ($cars as $car): ?>
            <tr>
                <td data-label="Car"><?= e($car["brand"] . " " . $car["model"]) ?></td>
                <td data-label="Year"><?= e($car["year"]) ?></td>
                <td data-label="Type"><?= e($car["type_name"]) ?></td>
                <td data-label="Daily rate" class="num"><?= e(money($car["daily_rate"])) ?></td>
                <td data-label="Status"><?= status_badge($car["active_rentals"] > 0 ? "Rented" : "Available") ?></td>
                <td data-label="Next booking"><?= $car["next_booking"] ? e(nice_date($car["next_booking"])) : '<span class="subtle">None</span>' ?></td>
                <td data-label="Times rented" class="num"><?= (int) $car["rental_count"] ?></td>
                <td data-label="Actions"><a class="btn btn-small btn-secondary" href="add_rental.php?car_id=<?= (int) $car["car_id"] ?>">Book this car</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="no-matches" id="cars-table-no-matches">No cars match your search.</p>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
