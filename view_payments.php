<?php
/**
 * Payments page
 * Lists every payment with the customer and car it was for.
 *
 * Joins 4 tables:
 *   payments -> rentals    (which rental the payment is for)
 *   rentals  -> customers  (who paid)
 *   rentals  -> cars       (which car was rented)
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

$sql = "
    SELECT p.payment_id, p.payment_date, p.amount, p.payment_method,
           r.rental_id, r.rental_date, r.return_date,
           cu.first_name, cu.last_name,
           c.brand, c.model
    FROM payments p
    INNER JOIN rentals r    ON p.rental_id = r.rental_id
    INNER JOIN customers cu ON r.customer_id = cu.customer_id
    INNER JOIN cars c       ON r.car_id = c.car_id
    ORDER BY p.payment_date DESC, p.payment_id DESC";

$payments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Total collected per payment method
$by_method = $conn->query("
    SELECT payment_method, COUNT(*) AS payment_count, SUM(amount) AS total
    FROM payments
    GROUP BY payment_method
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

$total_collected = array_sum(array_column($payments, "amount"));

$page_title  = "Payments";
$active_page = "view_payments.php";
require __DIR__ . "/includes/header.php";
?>

<div class="page-head">
    <div>
        <h1>Payments</h1>
        <p><?= count($payments) ?> <?= count($payments) === 1 ? "payment" : "payments" ?>, <?= e(money($total_collected)) ?> collected.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="add_payment.php">Record payment</a>
    </div>
</div>

<?php if (count($by_method) > 0): ?>
<section class="figures" aria-label="Collected by payment method">
    <?php foreach ($by_method as $m): ?>
        <div class="figure">
            <strong><?= e(money($m["total"])) ?></strong>
            <span><?= e($m["payment_method"]) ?>, <?= (int) $m["payment_count"] ?> <?= $m["payment_count"] == 1 ? "payment" : "payments" ?></span>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<div class="toolbar">
    <input type="search" placeholder="Search by customer, car or method" aria-label="Search payments" data-table-search="#payments-table">
</div>

<div class="table-wrap">
    <table class="stack" id="payments-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Car</th>
                <th>Rental</th>
                <th>Method</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($payments) === 0): ?>
            <tr class="empty-row"><td colspan="7">No payments yet. <a href="add_payment.php">Record a payment</a></td></tr>
        <?php endif; ?>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td data-label="Payment #"><?= (int) $p["payment_id"] ?></td>
                <td data-label="Date"><?= e(nice_date($p["payment_date"])) ?></td>
                <td data-label="Customer"><?= e($p["first_name"] . " " . $p["last_name"]) ?></td>
                <td data-label="Car"><?= e($p["brand"] . " " . $p["model"]) ?></td>
                <td data-label="Rental">#<?= (int) $p["rental_id"] ?> <span class="subtle"><?= e(nice_date($p["rental_date"])) ?> to <?= e(nice_date($p["return_date"])) ?></span></td>
                <td data-label="Method"><?= e($p["payment_method"]) ?></td>
                <td data-label="Amount" class="num"><?= e(money($p["amount"])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if (count($payments) > 0): ?>
        <tfoot>
            <tr>
                <td colspan="6">Total collected</td>
                <td class="num" data-label="Amount"><?= e(money($total_collected)) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <p class="no-matches" id="payments-table-no-matches">No payments match your search.</p>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
