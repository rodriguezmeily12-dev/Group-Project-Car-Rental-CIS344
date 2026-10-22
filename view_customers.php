<?php
/**
 * Customers page
 * Lists every customer with how many rentals they have and how much
 * they have paid in total.
 *
 *   LEFT JOIN rentals   -> keeps customers with no rentals (count = 0)
 *   LEFT JOIN payments  -> adds up what each customer has paid
 * COUNT(DISTINCT ...) makes sure a rental with several payments is
 * still counted once.
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

$sql = "
    SELECT cu.customer_id, cu.first_name, cu.last_name, cu.email, cu.phone,
           COUNT(DISTINCT r.rental_id) AS rental_count,
           COALESCE(SUM(p.amount), 0)  AS total_paid
    FROM customers cu
    LEFT JOIN rentals r  ON r.customer_id = cu.customer_id
    LEFT JOIN payments p ON p.rental_id = r.rental_id
    GROUP BY cu.customer_id, cu.first_name, cu.last_name, cu.email, cu.phone
    ORDER BY cu.last_name, cu.first_name";

$customers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

/** Show a 10-digit US number as (718) 555-0123; leave other lengths as they are. */
function format_phone(string $digits): string
{
    if (strlen($digits) === 10) {
        return "(" . substr($digits, 0, 3) . ") " . substr($digits, 3, 3) . "-" . substr($digits, 6);
    }
    return $digits;
}

$page_title  = "Customers";
$active_page = "view_customers.php";
require __DIR__ . "/includes/header.php";
?>

<div class="page-head">
    <div>
        <h1>Customers</h1>
        <p><?= count($customers) ?> customers on file.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="add_customer.php">Add customer</a>
    </div>
</div>

<div class="toolbar">
    <input type="search" placeholder="Search by name, email or phone" aria-label="Search customers" data-table-search="#customers-table">
</div>

<div class="table-wrap">
    <table class="stack" id="customers-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th class="num">Rentals</th>
                <th class="num">Total paid</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($customers) === 0): ?>
            <tr class="empty-row"><td colspan="7">No customers yet. <a href="add_customer.php">Add the first one</a></td></tr>
        <?php endif; ?>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td data-label="ID"><?= (int) $c["customer_id"] ?></td>
                <td data-label="Name"><?= e($c["first_name"] . " " . $c["last_name"]) ?></td>
                <td data-label="Email"><?= e($c["email"]) ?></td>
                <td data-label="Phone"><?= e(format_phone($c["phone"])) ?></td>
                <td data-label="Rentals" class="num"><?= (int) $c["rental_count"] ?></td>
                <td data-label="Total paid" class="num"><?= e(money($c["total_paid"])) ?></td>
                <td data-label="Actions">
                    <div class="actions">
                        <a class="btn btn-small btn-secondary" href="add_rental.php?customer_id=<?= (int) $c["customer_id"] ?>">New rental</a>
                        <form class="inline-form" method="POST" action="delete_customer.php"
                              data-confirm="Delete <?= e($c["first_name"] . " " . $c["last_name"]) ?>? This can't be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="customer_id" value="<?= (int) $c["customer_id"] ?>">
                            <button type="submit" class="btn btn-small btn-danger">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="no-matches" id="customers-table-no-matches">No customers match your search.</p>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
