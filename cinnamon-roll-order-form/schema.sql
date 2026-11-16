-- =============================================================
-- Bachata Bakery — Weekly Preorder System
-- schema.sql
--
-- Run once on a fresh database. Safe to re-run: uses IF NOT EXISTS.
-- MySQL 8.x / MariaDB 10.6+ / utf8mb4 / InnoDB
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- orders
-- One row per checkout attempt that passed validation. Only rows
-- with payment_status = 'paid' are real orders; 'failed' rows are
-- kept for audit and their items are released back to the window.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id                INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    order_ref         VARCHAR(20)       NOT NULL UNIQUE,          -- e.g. BB-20261014-64C8
    window_id         DATE              NOT NULL,                 -- Wednesday that closes the order week
    customer_name     VARCHAR(120)      NOT NULL,
    customer_email    VARCHAR(180)      NOT NULL,
    customer_phone    VARCHAR(20)       DEFAULT NULL,
    quantity          SMALLINT UNSIGNED NOT NULL,                 -- total items in the order
    product_variant   VARCHAR(255)      DEFAULT NULL,             -- summary, e.g. "2× El Clásico, 1× Guava Tres Leches"
    items_json        TEXT              DEFAULT NULL,             -- [{id,name,qty,unit_cents,line_cents}]
    pickup_date       DATE              DEFAULT NULL,
    pickup_location   VARCHAR(60)       DEFAULT NULL,
    pickup_time       TIME              DEFAULT NULL,
    special_notes     TEXT              DEFAULT NULL,
    payment_status    ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    square_order_id   VARCHAR(80)       DEFAULT NULL,
    square_payment_id VARCHAR(80)       DEFAULT NULL,
    amount_cents      INT UNSIGNED      NOT NULL,                 -- total charged, in cents
    created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_window  (window_id),
    INDEX idx_status  (payment_status),
    INDEX idx_email   (customer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- order_caps
-- One row per order week (keyed by the Wednesday it closes).
-- rolls_sold / rolls_max count TOTAL ITEMS of any product.
-- Counters are pre-incremented when an order is reserved and
-- decremented if its payment fails.
-- force_closed lets the owner stop orders for the week early.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_caps (
    window_id      DATE              PRIMARY KEY,
    rolls_sold     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    orders_placed  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    rolls_max      SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    orders_max     SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    force_closed   TINYINT(1)        NOT NULL DEFAULT 0,
    updated_at     DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- store_settings
-- paused_until (Y-m-d): orders closed until this date (vacation)
-- pause_message: optional text shown while paused
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS store_settings (
    setting_key    VARCHAR(40)  PRIMARY KEY,
    setting_value  TEXT         DEFAULT NULL,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- checkout_attempts
-- Card-testing protection: recent attempts / declines per hashed IP.
-- Also records failed admin logins ('admin_fail') for the login lockout.
-- Rows older than 2 days are purged by the app.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS checkout_attempts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash     CHAR(64)        NOT NULL,
    outcome     ENUM('attempt','declined','paid','admin_fail') NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
