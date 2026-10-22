-- =====================================================================
-- DMM Bronx Auto System - JOIN queries used by the application
--
-- Each query below is used on a page of the app. You can paste any of
-- them into the SQL tab in phpMyAdmin to see the results.
-- =====================================================================

USE dmmbronxauto;


-- ---------------------------------------------------------------------
-- 1. Rental details (Dashboard, Rentals page, Record Payment form)
--    INNER JOIN customers, cars and cartypes: every rental has all three.
--    LEFT JOIN payments: keeps rentals that have no payment yet.
--    Payments are summed in a subquery first so a rental with several
--    payments still appears once.
--    Cost = days x daily rate (a same-day rental counts as 1 day).
-- ---------------------------------------------------------------------
SELECT r.rental_id,
       CONCAT(cu.first_name, ' ', cu.last_name) AS customer,
       CONCAT(c.brand, ' ', c.model)            AS car,
       t.type_name,
       r.rental_date, r.return_date, r.status,
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
) p ON p.rental_id = r.rental_id
ORDER BY r.rental_date DESC;


-- ---------------------------------------------------------------------
-- 2. Cars with type, rate and current availability (Cars page)
--    LEFT JOIN rentals keeps cars that have never been rented.
--    A car is "Rented" when it has an Active rental, so availability
--    is never stored twice and can't get out of sync.
-- ---------------------------------------------------------------------
SELECT c.car_id, c.brand, c.model, c.year,
       t.type_name, t.daily_rate,
       COUNT(r.rental_id) AS times_rented,
       CASE WHEN SUM(CASE WHEN r.status = 'Active' THEN 1 ELSE 0 END) > 0
            THEN 'Rented' ELSE 'Available' END AS availability,
       MIN(CASE WHEN r.status = 'Booked' THEN r.rental_date END) AS next_booking
FROM cars c
INNER JOIN cartypes t ON c.type_id = t.type_id
LEFT JOIN rentals r   ON r.car_id = c.car_id
GROUP BY c.car_id, c.brand, c.model, c.year, t.type_name, t.daily_rate
ORDER BY t.daily_rate, c.brand, c.model;


-- ---------------------------------------------------------------------
-- 3. Customers with number of rentals and total paid (Customers page)
--    LEFT JOINs keep customers with no rentals (they show 0).
--    COUNT(DISTINCT) avoids counting a rental twice when it has
--    more than one payment.
-- ---------------------------------------------------------------------
SELECT cu.customer_id, cu.first_name, cu.last_name, cu.email, cu.phone,
       COUNT(DISTINCT r.rental_id) AS rental_count,
       COALESCE(SUM(p.amount), 0)  AS total_paid
FROM customers cu
LEFT JOIN rentals r  ON r.customer_id = cu.customer_id
LEFT JOIN payments p ON p.rental_id = r.rental_id
GROUP BY cu.customer_id, cu.first_name, cu.last_name, cu.email, cu.phone
ORDER BY cu.last_name, cu.first_name;


-- ---------------------------------------------------------------------
-- 4. Payments with the customer and car (Payments page)
--    payments -> rentals -> customers, and rentals -> cars
-- ---------------------------------------------------------------------
SELECT p.payment_id, p.payment_date, p.amount, p.payment_method,
       r.rental_id,
       CONCAT(cu.first_name, ' ', cu.last_name) AS customer,
       CONCAT(c.brand, ' ', c.model)            AS car
FROM payments p
INNER JOIN rentals r    ON p.rental_id = r.rental_id
INNER JOIN customers cu ON r.customer_id = cu.customer_id
INNER JOIN cars c       ON r.car_id = c.car_id
ORDER BY p.payment_date DESC, p.payment_id DESC;


-- ---------------------------------------------------------------------
-- 5. Double-booking check (Add Rental form)
--    Finds a Booked or Active rental of the same car whose dates overlap
--    the new dates. Two ranges overlap when each one starts on or before
--    the day the other ends. In the app the 3 values come from the form
--    through a prepared statement; here they are example values
--    (car 1, Nov 12 to Nov 14, 2026), which clash with rental 7.
-- ---------------------------------------------------------------------
SELECT rental_id, rental_date, return_date
FROM rentals
WHERE car_id = 1
  AND status IN ('Booked', 'Active')
  AND rental_date <= '2026-11-14'
  AND return_date >= '2026-11-12';


-- ---------------------------------------------------------------------
-- 6. Can a booked rental start? (Start rental button)
--    A self join: rentals joined to rentals, to find another Active
--    rental of the same car. Example: rental 7.
-- ---------------------------------------------------------------------
SELECT other.rental_id AS car_still_out_on
FROM rentals r
INNER JOIN rentals other ON other.car_id = r.car_id
WHERE r.rental_id = 7
  AND other.rental_id <> r.rental_id
  AND other.status = 'Active';


-- ---------------------------------------------------------------------
-- 7. Report: income by car type
--    cartypes -> cars -> rentals -> payments, all LEFT JOINs so every
--    type is listed even if nothing has been paid for it yet.
-- ---------------------------------------------------------------------
SELECT t.type_name,
       COUNT(DISTINCT c.car_id)    AS cars,
       COUNT(DISTINCT r.rental_id) AS rentals,
       COALESCE(SUM(p.amount), 0)  AS collected
FROM cartypes t
LEFT JOIN cars c     ON c.type_id = t.type_id
LEFT JOIN rentals r  ON r.car_id = c.car_id
LEFT JOIN payments p ON p.rental_id = r.rental_id
GROUP BY t.type_id, t.type_name
ORDER BY collected DESC;
