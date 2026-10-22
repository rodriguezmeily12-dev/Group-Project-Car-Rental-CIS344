<?php
/**
 * Record Payment page
 * Saves a payment for a rental that still has money owed. The balance
 * comes from the joined rental details (cost minus payments so far),
 * and a payment can't be more than the balance.
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

$errors = [];
$today = date("Y-m-d");

// Rentals that still owe money, for the dropdown (keyed by rental_id)
$owing = [];
foreach (fetch_rentals($conn, null, "newest") as $r) {
    if ((float) $r["balance"] > 0) {
        $owing[(int) $r["rental_id"]] = $r;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_is_valid()) {
        $errors[] = "Your session expired. Please submit the form again.";
    }

    $rental_id    = filter_var(input("rental_id"), FILTER_VALIDATE_INT);
    $amount       = input("amount");
    $payment_date = input("payment_date");
    $method       = input("payment_method");

    // Validation
    if (!isset($owing[$rental_id])) {
        $errors[] = "Choose a rental from the list.";
    }
    if (!preg_match("/^\d+(\.\d{1,2})?$/", $amount) || (float) $amount <= 0) {
        $errors[] = "Enter an amount greater than zero, like 250 or 250.00.";
    } elseif (isset($owing[$rental_id])) {
        // Compare in cents to avoid rounding problems with decimals
        $amount_cents  = (int) round((float) $amount * 100);
        $balance_cents = (int) round((float) $owing[$rental_id]["balance"] * 100);
        if ($amount_cents > $balance_cents) {
            $errors[] = "The amount can't be more than the balance owed ("
                      . money($owing[$rental_id]["balance"]) . ").";
        }
    }
    if (!is_valid_date($payment_date)) {
        $errors[] = "Enter the payment date.";
    } elseif ($payment_date > $today) {
        $errors[] = "The payment date can't be in the future.";
    }
    if (!in_array($method, PAYMENT_METHODS, true)) {
        $errors[] = "Choose a payment method.";
    }

    // Save the payment
    if (count($errors) === 0) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO payments (rental_id, amount, payment_date, payment_method)
                VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idss", $rental_id, $amount, $payment_date, $method);
            $stmt->execute();
            $stmt->close();

            set_flash("success", "Payment of " . money($amount) . " was recorded for rental #$rental_id.");
            redirect("view_payments.php");
        } catch (mysqli_sql_exception $e) {
            error_log("Add payment failed: " . $e->getMessage());
            $errors[] = "The payment couldn't be saved. Please try again.";
        }
    }
}

// Pre-select a rental when coming from a "Record payment" button
$selected_rental = (int) ($_POST["rental_id"] ?? $_GET["rental_id"] ?? 0);
$selected_method = $_POST["payment_method"] ?? "";

$page_title  = "Record Payment";
$active_page = "view_payments.php";
require __DIR__ . "/includes/header.php";
?>

<div class="page-head">
    <div>
        <h1>Record payment</h1>
        <p>Only rentals with a balance owed are listed.</p>
    </div>
</div>

<div class="panel panel-narrow">
    <?php if (count($owing) === 0): ?>
        <p>Every rental is paid in full, so there's nothing to record.</p>
        <a class="btn btn-secondary" href="view_payments.php">Back to payments</a>
    <?php else: ?>

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

    <form method="POST" action="add_payment.php" id="payment-form">
        <?= csrf_field() ?>

        <div class="form-field">
            <label for="rental_id">Rental</label>
            <select id="rental_id" name="rental_id" required>
                <option value="">Choose a rental</option>
                <?php foreach ($owing as $id => $r): ?>
                    <option value="<?= $id ?>" data-balance="<?= e($r["balance"]) ?>"<?= $selected_rental === $id ? " selected" : "" ?>>
                        <?= e("#$id " . $r["first_name"] . " " . $r["last_name"] . ", " . $r["brand"] . " " . $r["model"]
                            . " (" . money($r["balance"]) . " owed)") ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="hint" id="balance-hint" aria-live="polite"></span>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="amount">Amount ($)</label>
                <input type="number" id="amount" name="amount" min="0.01" step="0.01" required value="<?= old("amount") ?>">
            </div>
            <div class="form-field">
                <label for="payment_date">Payment date</label>
                <input type="date" id="payment_date" name="payment_date" max="<?= e($today) ?>" required value="<?= old("payment_date", $today) ?>">
            </div>
        </div>

        <div class="form-field">
            <label for="payment_method">Payment method</label>
            <select id="payment_method" name="payment_method" required>
                <option value="">Choose a method</option>
                <?php foreach (PAYMENT_METHODS as $m): ?>
                    <option value="<?= e($m) ?>"<?= $selected_method === $m ? " selected" : "" ?>><?= e($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="page-actions">
            <button type="submit" class="btn">Record payment</button>
            <a class="btn btn-secondary" href="view_payments.php">Cancel</a>
        </div>
    </form>

    <?php endif; ?>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
