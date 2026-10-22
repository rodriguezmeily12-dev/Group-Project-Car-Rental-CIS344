<?php
/**
 * Rentals page
 * Lists rentals with the customer, car, cost and payments joined in
 * (see RENTAL_DETAILS_SQL in includes/functions.php).
 * Can be filtered by status: view_rentals.php?status=Active
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

// Only accept a status from the allowed list; anything else shows all rentals
$status = $_GET["status"] ?? "";
if (!in_array($status, RENTAL_STATUSES, true)) {
    $status = null;
}

$rentals = fetch_rentals($conn, $status, "newest");

// Totals for the bottom row
$total_cost = array_sum(array_column($rentals, "total_cost"));
$total_paid = array_sum(array_column($rentals, "amount_paid"));
$total_due  = array_sum(array_map(fn ($r) => max((float) $r["balance"], 0), $rentals));

$page_title  = "Rentals";
$active_page = "view_rentals.php";
require __DIR__ . "/includes/header.php";
?>

<div class="page-head">
    <div>
        <h1>Rentals</h1>
        <p><?= count($rentals) ?> <?= $status ? e(strtolower($status)) . " " : "" ?><?= count($rentals) === 1 ? "rental" : "rentals" ?>.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="add_rental.php">Add rental</a>
    </div>
</div>

<div class="toolbar">
    <nav class="filters" aria-label="Filter by status">
        <a href="view_rentals.php"<?= $status === null ? ' class="active" aria-current="page"' : "" ?>>All</a>
        <?php foreach (RENTAL_STATUSES as $s): ?>
            <a href="view_rentals.php?status=<?= e($s) ?>"<?= $status === $s ? ' class="active" aria-current="page"' : "" ?>><?= e($s) ?></a>
        <?php endforeach; ?>
    </nav>
    <input type="search" placeholder="Search by customer or car" aria-label="Search rentals" data-table-search="#rentals-table">
</div>

<div class="table-wrap">
    <table class="stack" id="rentals-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Car</th>
                <th>Pickup</th>
                <th>Return</th>
                <th class="num">Days</th>
                <th class="num">Total</th>
                <th class="num">Paid</th>
                <th class="num">Balance</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($rentals) === 0): ?>
            <tr class="empty-row"><td colspan="11">No rentals found. <a href="add_rental.php">Add a rental</a></td></tr>
        <?php endif; ?>
        <?php foreach ($rentals as $r): ?>
            <tr>
                <td data-label="Rental #"><?= (int) $r["rental_id"] ?></td>
                <td data-label="Customer"><?= e($r["first_name"] . " " . $r["last_name"]) ?></td>
                <td data-label="Car"><?= e($r["brand"] . " " . $r["model"]) ?></td>
                <td data-label="Pickup" class="nowrap"><?= e(nice_date($r["rental_date"])) ?></td>
                <td data-label="Return" class="nowrap"><?= e(nice_date($r["return_date"])) ?></td>
                <td data-label="Days" class="num"><?= (int) $r["days"] ?></td>
                <td data-label="Total" class="num"><?= e(money($r["total_cost"])) ?></td>
                <td data-label="Paid" class="num"><?= e(money($r["amount_paid"])) ?></td>
                <td data-label="Balance" class="num<?= $r["balance"] > 0 ? " owed" : "" ?>"><?= e(money($r["balance"])) ?></td>
                <td data-label="Status"><?= rental_status($r) ?></td>
                <td data-label="Actions"><?= rental_actions($r, "view_rentals.php" . ($status ? "?status=" . $status : "")) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if (count($rentals) > 0): ?>
        <tfoot>
            <tr>
                <td colspan="6">Totals</td>
                <td class="num" data-label="Total"><?= e(money($total_cost)) ?></td>
                <td class="num" data-label="Paid"><?= e(money($total_paid)) ?></td>
                <td class="num<?= $total_due > 0 ? " owed" : "" ?>" data-label="Balance"><?= e(money($total_due)) ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <p class="no-matches" id="rentals-table-no-matches">No rentals match your search.</p>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
