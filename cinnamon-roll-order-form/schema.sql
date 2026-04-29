-- =============================================================
-- Bachata Bakery — Cinnamon Roll Order System
-- schema.sql
--
-- Run once on a fresh database. Safe to re-run: uses IF NOT EXISTS.
-- MySQL 8.x / utf8mb4 / InnoDB
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- orders
-- One row per submitted order. Only rows with payment_status
-- = 'paid' count toward caps. 'failed' rows are kept for audit.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id                INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    order_ref         VARCHAR(12)      NOT NULL UNIQUE,          -- e.g. BB-20260411-0007
    window_id         DATE             NOT NULL,                 -- Friday date of the order window
    customer_name     VARCHAR(120)     NOT NULL,
    customer_email    VARCHAR(180)     NOT NULL,
    customer_phone    VARCHAR(20)      DEFAULT NULL,
    quantity          TINYINT UNSIGNED NOT NULL,                 -- rolls ordered (1–12)
    product_variant   VARCHAR(80)      DEFAULT NULL,             -- e.g. "Classic Glazed"
    pickup_date       DATE             DEFAULT NULL,
    special_notes     TEXT             DEFAULT NULL,
    payment_status    ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    square_payment_id VARCHAR(80)      DEFAULT NULL,
    amount_cents      INT UNSIGNED     NOT NULL,                 -- total charged, in cents
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_window  (window_id),
    INDEX idx_status  (payment_status),
    INDEX idx_email   (customer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- order_caps
-- One row per weekend window (keyed by the Friday date).
-- rolls_sold / orders_placed are incremented inside the atomic
-- transaction in api/order.php AFTER a successful Square charge.
-- rolls_max / orders_max can be overridden by the admin dashboard.
-- force_closed lets the owner manually kill the form early.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_caps (
    window_id      DATE             PRIMARY KEY,                 -- Friday date of the window
    rolls_sold     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    orders_placed  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    rolls_max      TINYINT UNSIGNED NOT NULL DEFAULT 100,
    orders_max     TINYINT UNSIGNED NOT NULL DEFAULT 50,
    force_closed   TINYINT(1)       NOT NULL DEFAULT 0,
    updated_at     DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
