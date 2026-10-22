<?php
/**
 * Page header: <head>, top bar, navigation and the one-time message.
 * Each page sets $page_title and $active_page before including this file.
 */

$nav_links = [
    "index.php"          => "Dashboard",
    "view_cars.php"      => "Cars",
    "view_customers.php" => "Customers",
    "view_rentals.php"   => "Rentals",
    "view_payments.php"  => "Payments",
];

$active_page = $active_page ?? "";
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title ?? "DMM Bronx Auto") ?> | DMM Bronx Auto System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Semi+Condensed:wght@600;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php">
            <span class="plate">DMM</span>
            <span class="brand-name">Bronx Auto System</span>
        </a>

        <nav class="main-nav" aria-label="Main">
            <?php foreach ($nav_links as $href => $label): ?>
                <a href="<?= e($href) ?>"<?= $active_page === $href ? ' class="active" aria-current="page"' : "" ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<main class="page">
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash["type"]) ?>" role="status"><?= e($flash["message"]) ?></div>
<?php endif; ?>
