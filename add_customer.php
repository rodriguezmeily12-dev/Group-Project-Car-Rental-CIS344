<?php
/**
 * Add Customer page
 * Validates the form on the server, then saves the customer with a
 * prepared statement (prevents SQL injection).
 */
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/db_connect.php";

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_is_valid()) {
        $errors[] = "Your session expired. Please submit the form again.";
    }

    // Read and clean the input
    $first_name = input("first_name");
    $last_name  = input("last_name");
    $email      = input("email");
    $phone      = preg_replace("/[\s().-]/", "", input("phone")); // allow (718) 555-0123

    // Validation
    if ($first_name === "" || mb_strlen($first_name) > 50) {
        $errors[] = "First name is required (50 characters max).";
    }
    if ($last_name === "" || mb_strlen($last_name) > 50) {
        $errors[] = "Last name is required (50 characters max).";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = "Enter a valid email address, like name@example.com.";
    }
    if (!preg_match("/^[0-9]{10,15}$/", $phone)) {
        $errors[] = "Phone number must have 10 to 15 digits.";
    }

    // Each email can only belong to one customer
    if (count($errors) === 0) {
        $check = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "A customer with this email already exists.";
        }
        $check->close();
    }

    // Save the customer
    if (count($errors) === 0) {
        try {
            $stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $first_name, $last_name, $email, $phone);
            $stmt->execute();
            $stmt->close();

            set_flash("success", "$first_name $last_name was added.");
            redirect("view_customers.php");
        } catch (mysqli_sql_exception $e) {
            // 1062 = duplicate entry (the UNIQUE email constraint)
            $errors[] = $e->getCode() === 1062
                ? "A customer with this email already exists."
                : "The customer couldn't be saved. Please try again.";
            error_log("Add customer failed: " . $e->getMessage());
        }
    }
}

$page_title  = "Add Customer";
$active_page = "view_customers.php";
require __DIR__ . "/includes/header.php";
?>

<div class="page-head">
    <div>
        <h1>Add customer</h1>
        <p>All fields are required.</p>
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

    <form method="POST" action="add_customer.php">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-field">
                <label for="first_name">First name</label>
                <input type="text" id="first_name" name="first_name" maxlength="50" required value="<?= old("first_name") ?>">
            </div>
            <div class="form-field">
                <label for="last_name">Last name</label>
                <input type="text" id="last_name" name="last_name" maxlength="50" required value="<?= old("last_name") ?>">
            </div>
        </div>

        <div class="form-field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="100" required value="<?= old("email") ?>">
        </div>

        <div class="form-field">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" maxlength="20" required
                   pattern="[0-9\s\(\)\.\-]{10,20}" title="10 to 15 digits, for example 7185550123"
                   value="<?= old("phone") ?>">
            <span class="hint">Digits only, or a format like (718) 555-0123.</span>
        </div>

        <div class="page-actions">
            <button type="submit" class="btn">Add customer</button>
            <a class="btn btn-secondary" href="view_customers.php">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
