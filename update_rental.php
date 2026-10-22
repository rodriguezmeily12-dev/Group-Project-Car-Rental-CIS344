<?php
/**
 * Update Rental status (no page of its own; the buttons on the
 * Dashboard and Rentals pages post here)
 *
 * A rental moves one step at a time:
 *   start:  Booked -> Active    (the customer picks up the car)
 *   return: Active -> Returned  (the car comes back)
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

// Where to go back to afterwards (only pages from this list are allowed)
$return_to = input("return_to");
$allowed_pages = ["index.php", "view_rentals.php"];
$return_page = explode("?", $return_to)[0];
if (!in_array($return_page, $allowed_pages, true)) {
    $return_to = "view_rentals.php";
} elseif ($return_page === "view_rentals.php") {
    // keep the status filter only if it is a real status
    parse_str(parse_url($return_to, PHP_URL_QUERY) ?? "", $query);
    $return_to = in_array($query["status"] ?? "", RENTAL_STATUSES, true)
        ? "view_rentals.php?status=" . $query["status"]
        : "view_rentals.php";
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("view_rentals.php");
}

if (!csrf_is_valid()) {
    set_flash("error", "Your session expired. Please try again.");
    redirect($return_to);
}

$steps = [
    "start"  => ["from" => "Booked", "to" => "Active",   "done" => "started. The car is now out."],
    "return" => ["from" => "Active", "to" => "Returned", "done" => "marked as returned. The car is available again."],
];

$rental_id = filter_var(input("rental_id"), FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$step = $steps[input("step")] ?? null;

if ($rental_id === false || $step === null) {
    set_flash("error", "That update isn't valid.");
    redirect($return_to);
}

// Before starting a rental, make sure the car isn't still out with someone else
if ($step["to"] === "Active") {
    $stmt = $conn->prepare("
        SELECT other.rental_id
        FROM rentals r
        INNER JOIN rentals other ON other.car_id = r.car_id
        WHERE r.rental_id = ?
          AND other.rental_id <> r.rental_id
          AND other.status = 'Active'
        LIMIT 1");
    $stmt->bind_param("i", $rental_id);
    $stmt->execute();
    $still_out = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($still_out) {
        set_flash("error", "Rental #$rental_id can't start yet. The car is still out on rental #"
            . $still_out["rental_id"] . ". Mark that one returned first.");
        redirect($return_to);
    }
}

// Only update the rental if it is still at the expected step
$stmt = $conn->prepare("UPDATE rentals SET status = ? WHERE rental_id = ? AND status = ?");
$stmt->bind_param("sis", $step["to"], $rental_id, $step["from"]);
$stmt->execute();
$changed = $stmt->affected_rows === 1;
$stmt->close();

if ($changed) {
    set_flash("success", "Rental #$rental_id was " . $step["done"]);
} else {
    set_flash("error", "Rental #$rental_id couldn't be updated. It may have changed already; check its status below.");
}

redirect($return_to);
