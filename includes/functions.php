<?php
/**
 * Shared helper functions and settings used by every page.
 */

// Start the session (used for security tokens and one-time messages)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set("America/New_York");

// If an unexpected error happens anywhere, log the details and show a
// clean message instead of a blank page or a PHP error dump.
set_exception_handler(function (Throwable $e) {
    error_log("DMM Bronx Auto error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<div style="font-family:sans-serif;max-width:600px;margin:40px auto;padding:16px;'
       . 'border-left:4px solid #b42318;background:#fdecea">'
       . "<strong>Something went wrong.</strong> The error was saved to the PHP error log. "
       . '<a href="index.php">Go back to the dashboard</a></div>';
});

// Allowed values. These match the CHECK constraints in the database.
const RENTAL_STATUSES = ["Booked", "Active", "Returned"];
const PAYMENT_METHODS = ["Credit Card", "Debit Card", "Cash"];

/**
 * Base query for rental details, used by the dashboard, Rentals page and
 * Payments form. It joins 4 tables plus a payment summary:
 *   rentals -> customers  (who rented)
 *   rentals -> cars       (which car)
 *   cars    -> cartypes   (daily rate, to work out the cost)
 *   LEFT JOIN payments    (how much has been paid; LEFT JOIN keeps unpaid rentals)
 * Payments are summed in a subquery first, so a rental with several
 * payments still appears only once.
 */
const RENTAL_DETAILS_SQL = "
    SELECT r.rental_id, r.rental_date, r.return_date, r.status,
           cu.customer_id, cu.first_name, cu.last_name,
           c.car_id, c.brand, c.model, t.type_name, t.daily_rate,
           GREATEST(DATEDIFF(r.return_date, r.rental_date), 1) AS days,
           GREATEST(DATEDIFF(r.return_date, r.rental_date), 1) * t.daily_rate AS total_cost,
           COALESCE(p.amount_paid, 0) AS amount_paid,
           GREATEST(DATEDIFF(r.return_date, r.rental_date), 1) * t.daily_rate
               - COALESCE(p.amount_paid, 0) AS balance
    FROM rentals r
    INNER JOIN customers cu ON r.customer_id = cu.customer_id
    INNER JOIN cars c       ON r.car_id = c.car_id
    INNER JOIN cartypes t   ON c.type_id = t.type_id
    LEFT JOIN (
        SELECT rental_id, SUM(amount) AS amount_paid
        FROM payments
        GROUP BY rental_id
    ) p ON p.rental_id = r.rental_id";

/**
 * Get rentals with all their details.
 * $status  optional filter: Booked, Active or Returned
 * $sort    one of the keys in $sort_options below (never raw user input)
 */
function fetch_rentals(mysqli $conn, ?string $status = null, string $sort = "newest"): array
{
    $sort_options = [
        "newest"          => "r.rental_date DESC, r.rental_id DESC",
        "pickup_soonest"  => "r.rental_date ASC, r.rental_id ASC",
        "return_soonest"  => "r.return_date ASC, r.rental_id ASC",
    ];
    $order_by = $sort_options[$sort] ?? $sort_options["newest"];

    if ($status !== null) {
        // Prepared statement: the status value is sent separately from the SQL
        $stmt = $conn->prepare(RENTAL_DETAILS_SQL . " WHERE r.status = ? ORDER BY " . $order_by);
        $stmt->bind_param("s", $status);
    } else {
        $stmt = $conn->prepare(RENTAL_DETAILS_SQL . " ORDER BY " . $order_by);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Escape text before printing it in HTML (prevents XSS attacks). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

/** Read a text field from a submitted form, trimmed. Returns "" if missing. */
function input(string $field): string
{
    $value = $_POST[$field] ?? "";
    return is_string($value) ? trim($value) : "";
}

/** Put a submitted value back into a form field after a validation error. */
function old(string $field, string $default = ""): string
{
    return e($_POST[$field] ?? $default);
}

/** Format a number as dollars, for example 1250 -> $1,250.00 */
function money($amount): string
{
    return "$" . number_format((float) $amount, 2);
}

/** Format a YYYY-MM-DD date as "Oct 15, 2026". */
function nice_date(?string $date): string
{
    $d = DateTime::createFromFormat("Y-m-d", (string) $date);
    return $d ? $d->format("M j, Y") : (string) $date;
}

/** Check that a string is a real date in YYYY-MM-DD format. */
function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat("Y-m-d", $date);
    return $d !== false && $d->format("Y-m-d") === $date;
}

/** Colored label for a status, for example "Active" or "Available". */
function status_badge(string $status): string
{
    $class = "badge-" . strtolower($status);
    return '<span class="badge ' . e($class) . '">' . e($status) . "</span>";
}

/** Status label for a rental. An Active rental past its return date also gets "Overdue". */
function rental_status(array $rental): string
{
    $html = status_badge($rental["status"]);
    if ($rental["status"] === "Active" && $rental["return_date"] < date("Y-m-d")) {
        $html .= " " . status_badge("Overdue");
    }
    return $html;
}

/**
 * Buttons that move a rental to its next step (Booked -> Active -> Returned),
 * plus a "Record payment" link while money is still owed.
 * $return_to is the page to come back to afterwards.
 */
function rental_actions(array $rental, string $return_to): string
{
    $id = (int) $rental["rental_id"];
    $html = "";

    if ($rental["status"] === "Booked") {
        $html .= rental_step_form($id, "start", "Start rental", "Hand over the car and start rental #$id?", $return_to);
    } elseif ($rental["status"] === "Active") {
        $html .= rental_step_form($id, "return", "Mark returned", "Mark rental #$id as returned?", $return_to);
    }

    if ((float) $rental["balance"] > 0) {
        $html .= '<a class="btn btn-small btn-secondary" href="add_payment.php?rental_id=' . $id . '">Record payment</a>';
    }

    return $html === "" ? '<span class="subtle">Done</span>' : '<div class="actions">' . $html . "</div>";
}

/** Small form with one button that posts to update_rental.php. */
function rental_step_form(int $rental_id, string $step, string $label, string $question, string $return_to): string
{
    return '<form class="inline-form" method="POST" action="update_rental.php" data-confirm="' . e($question) . '">'
         . csrf_field()
         . '<input type="hidden" name="rental_id" value="' . $rental_id . '">'
         . '<input type="hidden" name="step" value="' . e($step) . '">'
         . '<input type="hidden" name="return_to" value="' . e($return_to) . '">'
         . '<button type="submit" class="btn btn-small">' . e($label) . "</button>"
         . "</form>";
}

/* ---------------------------------------------------------------------
 * CSRF protection
 * Every form includes a secret token. A form submitted from another
 * website won't have it, so the request is rejected.
 * ------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $sent = $_POST["csrf_token"] ?? "";
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

/* ---------------------------------------------------------------------
 * Flash messages: a message saved before a redirect and shown once on
 * the next page, for example "Customer added."
 * ------------------------------------------------------------------- */

function set_flash(string $type, string $message): void
{
    $_SESSION["flash"] = ["type" => $type, "message" => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION["flash"])) {
        return null;
    }
    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    return $flash;
}

/** Send the browser to another page and stop running this one. */
function redirect(string $page): void
{
    header("Location: " . $page);
    exit();
}
