<?php
/**
 * Delete Customer (no page of its own; the form on Customers posts here)
 *
 * A customer with rentals can't be deleted, because their rentals and
 * payments are part of the business records. The foreign key on
 * rentals.customer_id enforces this in the database too.
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

// Only accept POST requests, so a plain link can't delete anything
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("view_customers.php");
}

if (!csrf_is_valid()) {
    set_flash("error", "Your session expired. Please try again.");
    redirect("view_customers.php");
}

$customer_id = filter_var($_POST["customer_id"] ?? "", FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if ($customer_id === false) {
    set_flash("error", "No valid customer was selected.");
    redirect("view_customers.php");
}

// Find the customer and count their rentals (LEFT JOIN keeps customers with none)
$stmt = $conn->prepare("
    SELECT cu.first_name, cu.last_name, COUNT(r.rental_id) AS rental_count
    FROM customers cu
    LEFT JOIN rentals r ON r.customer_id = cu.customer_id
    WHERE cu.customer_id = ?
    GROUP BY cu.customer_id, cu.first_name, cu.last_name");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    set_flash("error", "That customer no longer exists.");
    redirect("view_customers.php");
}

$name = $customer["first_name"] . " " . $customer["last_name"];

if ($customer["rental_count"] > 0) {
    $count = (int) $customer["rental_count"];
    set_flash("error", "$name can't be deleted because they have $count "
        . ($count === 1 ? "rental" : "rentals") . " on record.");
    redirect("view_customers.php");
}

try {
    $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->close();
    set_flash("success", "$name was deleted.");
} catch (mysqli_sql_exception $e) {
    // 1451 = a foreign key blocked the delete (a rental was added meanwhile)
    error_log("Delete customer failed: " . $e->getMessage());
    set_flash("error", "$name couldn't be deleted because they have rentals on record.");
}

redirect("view_customers.php");
