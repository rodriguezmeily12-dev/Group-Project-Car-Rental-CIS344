<?php
/**
 * Database connection
 *
 * Uses the XAMPP defaults: user "root" with an empty password.
 * Change these four values if your MySQL setup is different.
 */

$db_host     = "localhost";
$db_user     = "root";
$db_password = "";
$db_name     = "dmmbronxauto";

// Make mysqli throw exceptions on errors, so every page can catch them
// and show a clear message instead of crashing.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Log the technical details, show the user something they can act on
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Can't connect to the database. Make sure MySQL is running in XAMPP "
       . "and that database/dmmbronxauto.sql has been imported in phpMyAdmin.");
}
