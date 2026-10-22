# DMM Bronx Auto System

A web application for running a small car rental business in the Bronx. Staff can see which cars are available, add customers, book rentals, move rentals from booked to active to returned, and record payments. Rental costs and balances are worked out from the database with SQL joins.

Built with PHP, MySQL/MariaDB, HTML, CSS and JavaScript.

## Features

- **Dashboard**: cars available now, cars out on rental, upcoming pickups and the total balance owed, plus quick actions.
- **Cars**: every car with its type, daily rate, live availability, next booking and how many times it has been rented.
- **Customers**: add, search and delete customers. Each customer shows their number of rentals and total paid. Customers with rental history can't be deleted.
- **Rentals**: book a car with a live cost estimate, filter by status, start a rental, mark it returned, and see the cost, amount paid and balance of every rental. Double bookings are blocked.
- **Payments**: record payments (deposits or full amounts) against a rental's balance, and see totals by payment method.
- **Responsive design**: on a phone, tables turn into stacked cards and the menu scrolls sideways.

## Tech stack

| Part | Technology |
|---|---|
| Server-side | PHP 8 (mysqli with prepared statements) |
| Database | MySQL 8 or MariaDB 10.4+ |
| Front end | HTML, CSS, JavaScript (no frameworks) |
| Local server | XAMPP (Apache + MariaDB) |

## Folder structure

```
dmmbronxauto/
├── index.php              Dashboard
├── view_cars.php          Cars list
├── view_customers.php     Customers list (with delete)
├── view_rentals.php       Rentals list (status filter, actions)
├── view_payments.php      Payments list
├── add_customer.php       Add customer form
├── add_rental.php         Add rental form
├── add_payment.php        Record payment form
├── update_rental.php      Starts or returns a rental (form handler)
├── delete_customer.php    Deletes a customer (form handler)
├── includes/
│   ├── db_connect.php     Database connection settings
│   ├── functions.php      Shared helpers, security and the main rental query
│   ├── header.php         Page head, top bar and navigation
│   └── footer.php         Page footer
├── assets/
│   ├── css/style.css      All styles, including the phone layout
│   └── js/script.js       Form checks, cost estimate, table search
├── database/
│   ├── dmmbronxauto.sql   Creates the database, tables and sample data
│   └── queries.sql        The JOIN queries used by the app, explained
└── README.md
```

## How to run it

### 1. Install XAMPP
Download XAMPP from [apachefriends.org](https://www.apachefriends.org) and install it.

### 2. Copy the project
Put the `dmmbronxauto` folder inside XAMPP's `htdocs` folder:
- Mac: `/Applications/XAMPP/htdocs/dmmbronxauto`
- Windows: `C:\xampp\htdocs\dmmbronxauto`

### 3. Start the servers
Open the XAMPP control panel (on Mac, the **Manage Servers** tab) and start **Apache** and **MySQL**.

If you also have a separate MySQL installed (for example the one MySQL Workbench uses), stop it first. Only one can use port 3306 at a time.

### 4. Create the database
1. Go to [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Click the **Import** tab at the top (on the home screen, not inside a database).
3. Choose `database/dmmbronxauto.sql` and click **Import**.

This creates the `dmmbronxauto` database with 5 tables and sample data. Importing it again resets everything to the sample data.

### 5. Open the app
Go to [http://localhost/dmmbronxauto](http://localhost/dmmbronxauto).

The connection settings are in `includes/db_connect.php`. They use XAMPP's defaults (user `root`, no password). Change them there if your setup is different.

## How to test it

Reset the sample data first by importing `database/dmmbronxauto.sql` again. Then try each of these:

| What to do | What should happen |
|---|---|
| Open each page in the menu | Every page loads with data and no errors |
| Add a customer with a phone like `(718) 555-0123` | Customer appears on the Customers page |
| Add a customer with an email that already exists | Error: a customer with this email already exists |
| Add a customer with an invalid email or a 5-digit phone | The form shows what to fix and keeps what you typed |
| Delete Walter White | Blocked, because he has rentals on record |
| Delete the customer you just added | Customer is deleted |
| Add a rental and change the dates | The estimated cost updates as you type |
| Book the Toyota Corolla for Nov 12 to Nov 14, 2026 | Blocked: it's already booked Nov 10 to Nov 15 (rental #7) |
| Pick a return date before the pickup date | Blocked with a clear message |
| Click **Mark returned** on the Ferrari rental | Status becomes Returned and the Ferrari shows Available on the Cars page |
| Click **Start rental** on a booked rental | Status becomes Active and the car shows Rented |
| Record a payment larger than the balance | Blocked: the amount can't be more than the balance owed |
| Record a valid payment | Payment appears on the Payments page and the rental's balance goes down |
| Make the browser window narrow (or open it on a phone) | Tables turn into stacked cards; nothing overflows |

## Database design

Five tables. Every table has a primary key, and the relationships are enforced with foreign keys.

| Table | What it stores | Key columns |
|---|---|---|
| `cartypes` | Car categories and their price per day | `type_id` PK, `type_name` UNIQUE, `daily_rate` |
| `cars` | Each car in the fleet | `car_id` PK, `type_id` FK → cartypes |
| `customers` | People who rent cars | `customer_id` PK, `email` UNIQUE |
| `rentals` | A customer renting a car for certain dates | `rental_id` PK, `customer_id` FK → customers, `car_id` FK → cars |
| `payments` | Money received for a rental | `payment_id` PK, `rental_id` FK → rentals |

Relationships (all one-to-many):
- One car type has many cars.
- One customer has many rentals.
- One car has many rentals over time.
- One rental can have many payments (for example a deposit, then the rest).

Design choices:
- **No stored availability or cost.** A car's availability comes from its rentals, and a rental's cost is days × the daily rate. Both are calculated with joins, so they can never get out of sync with the real data.
- **Constraints in the database**, not only in PHP: `NOT NULL` on required columns, `UNIQUE` email, and `CHECK` constraints so a return date can't be before the pickup date, a status must be Booked, Active or Returned, and a payment must be more than zero.
- **`ON DELETE RESTRICT`** on the foreign keys, so a customer or car with rental history can't be deleted by accident.
- **Indexes** on every foreign key, plus one on `(car_id, rental_date, return_date)` to make the double-booking check fast.

## Use of joins

| Where | Join | Why |
|---|---|---|
| Rentals, Dashboard, Record Payment | rentals ⋈ customers ⋈ cars ⋈ cartypes, LEFT JOIN payments (summed) | Shows who rented which car, the cost (days × daily rate), amount paid and balance. LEFT JOIN keeps unpaid rentals. |
| Cars | cars ⋈ cartypes, LEFT JOIN rentals | Type, rate, availability and next booking. LEFT JOIN keeps cars never rented. |
| Customers | customers LEFT JOIN rentals LEFT JOIN payments | Rentals per customer and total paid. Customers with no rentals still show. |
| Payments | payments ⋈ rentals ⋈ customers, rentals ⋈ cars | Who paid and for which car. |
| Add Rental | cars ⋈ cartypes | Car dropdown shows type and daily rate for the cost estimate. |
| Start rental | rentals ⋈ rentals (self join) | Stops a car from being handed out while it's still out with another customer. |
| Delete customer | customers LEFT JOIN rentals | Counts rentals before allowing a delete. |

All of these queries are in `database/queries.sql` with comments, ready to run in phpMyAdmin.

## Security

- **Prepared statements** for every query that uses form or URL input, which prevents SQL injection.
- **Server-side validation** of every field (required fields, email format, phone digits, real dates, amounts), and values from dropdowns are checked against the allowed list.
- **Output escaping** with `htmlspecialchars` on everything printed to the page, which prevents cross-site scripting (XSS).
- **CSRF tokens** on every form, so another website can't submit forms on a user's behalf.
- **POST only** for actions that change data (deleting a customer, changing a rental's status).
- **Friendly error handling**: database errors are logged, and the user sees a clear message instead of a crash.
- **`.htaccess` files** block the browser from opening the `includes` and `database` folders directly.
