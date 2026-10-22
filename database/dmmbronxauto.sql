-- =====================================================================
-- DMM Bronx Auto System
-- Car Rental Management Application - database script
--
-- Creates the dmmbronxauto database, its 5 tables, and sample data.
-- Works with MariaDB 10.4+ (XAMPP) and MySQL 8.0+.
--
-- Import it in phpMyAdmin (Import tab) or run it in MySQL Workbench.
-- Running it again resets the database to the sample data.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS dmmbronxauto
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE dmmbronxauto;

-- Drop old tables first. Child tables go first because of the foreign keys.
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS rentals;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS cartypes;
DROP TABLE IF EXISTS customers;


-- ---------------------------------------------------------------------
-- cartypes: each category of car and its price per day
-- ---------------------------------------------------------------------
CREATE TABLE cartypes (
    type_id     INT           NOT NULL AUTO_INCREMENT,
    type_name   VARCHAR(50)   NOT NULL,
    daily_rate  DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (type_id),
    CONSTRAINT uq_cartypes_name UNIQUE (type_name),
    CONSTRAINT chk_cartypes_rate CHECK (daily_rate > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- cars: each vehicle in the fleet
-- A car's availability is NOT stored here. It is worked out from the
-- rentals table with a JOIN, so it can never get out of sync.
-- ---------------------------------------------------------------------
CREATE TABLE cars (
    car_id   INT          NOT NULL AUTO_INCREMENT,
    brand    VARCHAR(50)  NOT NULL,
    model    VARCHAR(50)  NOT NULL,
    year     SMALLINT     NOT NULL,
    type_id  INT          NOT NULL,
    PRIMARY KEY (car_id),
    KEY idx_cars_type (type_id),
    CONSTRAINT fk_cars_type FOREIGN KEY (type_id)
        REFERENCES cartypes (type_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_cars_year CHECK (year BETWEEN 1990 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- customers: people who rent cars
-- ---------------------------------------------------------------------
CREATE TABLE customers (
    customer_id  INT           NOT NULL AUTO_INCREMENT,
    first_name   VARCHAR(50)   NOT NULL,
    last_name    VARCHAR(50)   NOT NULL,
    email        VARCHAR(100)  NOT NULL,
    phone        VARCHAR(15)   NOT NULL,
    PRIMARY KEY (customer_id),
    CONSTRAINT uq_customers_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- rentals: one row per rental (customer + car + dates)
-- Status moves Booked -> Active -> Returned.
-- ---------------------------------------------------------------------
CREATE TABLE rentals (
    rental_id    INT          NOT NULL AUTO_INCREMENT,
    customer_id  INT          NOT NULL,
    car_id       INT          NOT NULL,
    rental_date  DATE         NOT NULL,
    return_date  DATE         NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'Booked',
    PRIMARY KEY (rental_id),
    KEY idx_rentals_customer (customer_id),
    -- speeds up the double-booking check (same car, overlapping dates)
    KEY idx_rentals_car_dates (car_id, rental_date, return_date),
    KEY idx_rentals_status (status),
    CONSTRAINT fk_rentals_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rentals_car FOREIGN KEY (car_id)
        REFERENCES cars (car_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_rentals_dates CHECK (return_date >= rental_date),
    CONSTRAINT chk_rentals_status CHECK (status IN ('Booked', 'Active', 'Returned'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- payments: money received for a rental (a rental can have several,
-- for example a deposit and then the balance)
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    payment_id      INT           NOT NULL AUTO_INCREMENT,
    rental_id       INT           NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_date    DATE          NOT NULL,
    payment_method  VARCHAR(20)   NOT NULL,
    PRIMARY KEY (payment_id),
    KEY idx_payments_rental (rental_id),
    CONSTRAINT fk_payments_rental FOREIGN KEY (rental_id)
        REFERENCES rentals (rental_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_payments_amount CHECK (amount > 0),
    CONSTRAINT chk_payments_method CHECK (payment_method IN ('Credit Card', 'Debit Card', 'Cash'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =====================================================================
-- Sample data
-- =====================================================================

INSERT INTO cartypes (type_id, type_name, daily_rate) VALUES
(1, 'Economy',  50.00),
(2, 'Luxury',  150.00),
(3, 'SUV',     100.00),
(4, 'Sports',  200.00);

INSERT INTO cars (car_id, brand, model, year, type_id) VALUES
(1, 'Toyota',        'Corolla',  2022, 1),
(2, 'BMW',           '7 Series', 2023, 2),
(3, 'Ford',          'Explorer', 2021, 3),
(4, 'Ferrari',       '488',      2023, 4),
(5, 'Lamborghini',   'Huracan',  2022, 4),
(6, 'Honda',         'Civic',    2024, 1),
(7, 'Mercedes-Benz', 'E-Class',  2024, 2),
(8, 'Chevrolet',     'Tahoe',    2023, 3);

INSERT INTO customers (customer_id, first_name, last_name, email, phone) VALUES
(1, 'Walter', 'White',   'walterhwhite02@email.com',       '1111111111'),
(2, 'Jesse',  'Pinkman', 'chilip5050@email.com',           '2222222222'),
(3, 'Son',    'Goku',    'SuperSaiyan@yahoo.com',          '3333333333'),
(4, 'Prince', 'Vegeta',  'PrinceOfAllSaiyans@yahoo.com',   '4444444444'),
(5, 'Gojo',   'Satoru',  'TheStrongest@icloud.com',        '5555555555'),
(6, 'Yuji',   'Itadori', 'JenniferLawrenceFan@icloud.com', '6666666666');

-- Rental cost = number of days x the car type's daily rate (worked out with a JOIN)
INSERT INTO rentals (rental_id, customer_id, car_id, rental_date, return_date, status) VALUES
(1, 1, 2, '2026-09-01', '2026-09-05', 'Returned'),  -- 4 days x 150 = 600
(2, 2, 1, '2026-09-10', '2026-09-15', 'Returned'),  -- 5 days x  50 = 250
(3, 5, 3, '2026-09-20', '2026-09-25', 'Returned'),  -- 5 days x 100 = 500
(4, 3, 4, '2026-10-15', '2026-10-25', 'Active'),    -- 10 days x 200 = 2000
(5, 1, 6, '2026-10-18', '2026-10-23', 'Active'),    -- 5 days x  50 = 250
(6, 4, 5, '2026-11-01', '2026-11-06', 'Booked'),    -- 5 days x 200 = 1000
(7, 6, 1, '2026-11-10', '2026-11-15', 'Booked');    -- 5 days x  50 = 250

INSERT INTO payments (payment_id, rental_id, amount, payment_date, payment_method) VALUES
(1, 1,  600.00, '2026-09-01', 'Credit Card'),
(2, 2,  250.00, '2026-09-10', 'Debit Card'),
(3, 3,  500.00, '2026-09-20', 'Credit Card'),
(4, 4, 1000.00, '2026-10-15', 'Credit Card'),  -- deposit, 1000 still owed
(5, 5,  250.00, '2026-10-18', 'Cash'),
(6, 6, 1000.00, '2026-10-18', 'Cash');
-- rental 7 has no payment yet, so it shows a balance due
