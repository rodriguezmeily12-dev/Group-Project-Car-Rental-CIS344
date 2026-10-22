<?php
/**
 * Add Rental page
 * Books a car for a customer. Before saving, it checks that the car
 * isn't already booked or out on those dates (no double bookings).
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

$errors = [];

// Customers for the dropdown
$customers = $conn->query("
    SELECT customer_id, first_name, last_name
    FROM customers
    ORDER BY last_name, first_name
")->fetch_all(MYSQLI_ASSOC);

// Cars for the dropdown, joined with cartypes to show the type and daily rate
$cars = $conn->query("
    SELECT c.car_id, c.brand, c.model, t.type_name, t.daily_rate
    FROM cars c
    INNER JOIN cartypes t ON c.type_id = t.type_id
    ORDER BY t.daily_rate, c.brand, c.model
")->fetch_all(MYSQLI_ASSOC);

$customer_ids = array_map("intval", array_column($customers, "customer_id"));
$car_ids      = array_map("intval", array_column($cars, "car_id"));

// New rentals start as Booked (future pickup) or Active (car leaves today)
$new_statuses = ["Booked", "Active"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_is_valid()) {
        $errors[] = "Your session expired. Please submit the form again.";
    }

    $customer_id = filter_var(input("customer_id"), FILTER_VALIDATE_INT);
    $car_id      = filter_var(input("car_id"), FILTER_VALIDATE_INT);
    $rental_date = input("rental_date");
    $return_date = input("return_date");
    $status      = input("status");

    // Validation: values must be real choices from the dropdowns
    if (!in_array($customer_id, $customer_ids, true)) {
        $errors[] = "Choose a customer from the list.";
    }
    if (!in_array($car_id, $car_ids, true)) {
        $errors[] = "Choose a car from the list.";
    }
    if (!is_valid_date($rental_date) || !is_valid_date($return_date)) {
        $errors[] = "Enter both a pickup date and a return date.";
    } elseif ($return_date < $rental_date) {
        $errors[] = "The return date must be on or after the pickup date.";
    }
    if (!in_array($status, $new_statuses, true)) {
        $errors[] = "Choose Booked or Active for the status.";
    }

    // No double bookings: look for another Booked/Active rental of this car
    // whose dates overlap. Two date ranges overlap when each one starts
    // before (or on the day) the other one ends.
    if (count($errors) === 0) {
        $stmt = $conn->prepare("
            SELECT rental_id, rental_date, return_date
            FROM rentals
            WHERE car_id = ?
              AND status IN ('Booked', 'Active')
              AND rental_date <= ?
              AND return_date >= ?
            ORDER BY rental_date
            LIMIT 1");
        $stmt->bind_param("iss", $car_id, $return_date, $rental_date);
        $stmt->execute();
        $clash = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($clash) {
            $errors[] = "This car is already booked from " . nice_date($clash["rental_date"])
                      . " to " . nice_date($clash["return_date"]) . " (rental #" . $clash["rental_id"]
                      . "). Pick other dates or another car.";
        }
    }

    // A car that hasn't come back yet can't be handed to someone else
    if (count($errors) === 0 && $status === "Active") {
        $stmt = $conn->prepare("SELECT rental_id FROM rentals WHERE car_id = ? AND status = 'Active' LIMIT 1");
        $stmt->bind_param("i", $car_id);
        $stmt->execute();
        $still_out = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($still_out) {
            $errors[] = "This car is still out on rental #" . $still_out["rental_id"] . ". Mark that rental returned first.";
        }
    }

    // Save the rental
    if (count($errors) === 0) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO rentals (customer_id, car_id, rental_date, return_date, status)
                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $customer_id, $car_id, $rental_date, $return_date, $status);
            $stmt->execute();
            $new_id = $stmt->insert_id;
            $stmt->close();

            set_flash("success", "Rental #$new_id was added for " . nice_date($rental_date)
                . " to " . nice_date($return_date) . ".");
            redirect("view_rentals.php");
        } catch (mysqli_sql_exception $e) {
            error_log("Add rental failed: " . $e->getMessage());
            $errors[] = "The rental couldn't be saved. Please try again.";
        }
    }
}

// Pre-select a customer or car when coming from the Customers or Cars page
$selected_customer = (int) ($_POST["customer_id"] ?? $_GET["customer_id"] ?? 0);
$selected_car      = (int) ($_POST["car_id"] ?? $_GET["car_id"] ?? 0);
$selected_status   = $_POST["status"] ?? "Booked";

$page_title  = "Add Rental";
$active_page = "view_rentals.php";
require __DIR__ . "/includes/header.php";
?>

<div class="page-head">
    <div>
        <h1>Add rental</h1>
        <p>The cost is the number of days times the car's daily rate.</p>
    </div>
</div>

<div class="panel panel-narrow">
    <?php if (count($errors) > 0): ?>
        <div class="alert alert-error" role="alert">
            Please fix the following:
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="add_rental.php" id="rental-form">
        <?= csrf_field() ?>

        <div class="form-field">
            <label for="customer_id">Customer</label>
            <select id="customer_id" name="customer_id" required>
                <option value="">Choose a customer</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int) $c["customer_id"] ?>"<?= $selected_customer === (int) $c["customer_id"] ? " selected" : "" ?>>
                        <?= e($c["last_name"] . ", " . $c["first_name"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="hint">Not listed? <a href="add_customer.php">Add a new customer</a> first.</span>
        </div>

        <div class="form-field">
            <label for="car_id">Car</label>
            <select id="car_id" name="car_id" required>
                <option value="">Choose a car</option>
                <?php foreach ($cars as $car): ?>
                    <option value="<?= (int) $car["car_id"] ?>" data-rate="<?= e($car["daily_rate"]) ?>"<?= $selected_car === (int) $car["car_id"] ? " selected" : "" ?>>
                        <?= e($car["brand"] . " " . $car["model"] . " (" . $car["type_name"] . ", " . money($car["daily_rate"]) . " a day)") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="rental_date">Pickup date</label>
                <input type="date" id="rental_date" name="rental_date" required value="<?= old("rental_date") ?>">
            </div>
            <div class="form-field">
                <label for="return_date">Return date</label>
                <input type="date" id="return_date" name="return_date" required value="<?= old("return_date") ?>">
                <span class="hint" id="date-hint" aria-live="polite"></span>
            </div>
        </div>

        <div class="form-field">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <?php foreach ($new_statuses as $s): ?>
                    <option value="<?= e($s) ?>"<?= $selected_status === $s ? " selected" : "" ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="hint">Booked: the customer picks the car up later. Active: the car leaves today.</span>
        </div>

        <p class="estimate" id="estimate" aria-live="polite">Pick a car and both dates to see the cost.</p>

        <div class="page-actions">
            <button type="submit" class="btn">Add rental</button>
            <a class="btn btn-secondary" href="view_rentals.php">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
