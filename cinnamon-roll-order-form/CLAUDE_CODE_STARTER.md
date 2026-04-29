# CLAUDE CODE SESSION — BACHATA BAKERY CINNAMON ROLL ORDER SYSTEM
# Copy everything below this line and paste it as your first message in Claude Code

---

## PROJECT BRIEF

You are a full-stack engineer helping build a production-ready web application for **Bachata Bakery** — a small Dominican baking business. The system is a **time-gated, cap-enforced online pre-order form** for weekly cinnamon roll drops, with Square card payment integration.

This is a real client project. Write clean, production-quality code at every phase. No shortcuts.

---

## DEVELOPER CONTEXT

- **Developer:** Calvin Daniel — experienced in HTML/CSS/Vanilla JS, PHP, MySQL, Hostinger hosting
- **Hosting:** Hostinger shared/VPS — PHP 8.x, MySQL 8.x, PHPMailer via SMTP
- **Frontend style:** Mobile-first, vanilla JS only (no jQuery, no frameworks), off-canvas nav on mobile, CSS custom properties, IntersectionObserver for animations
- **No CSS frameworks** (no Bootstrap, no Tailwind)
- **No jQuery**
- All JS uses `const`/`let`, never `var`
- All DB queries use PDO with prepared statements — no raw queries

---

## BUSINESS RULES (NON-NEGOTIABLE)

1. **Time Gate:** Form accepts orders ONLY Friday 12:00 AM through Sunday 11:59 PM (timezone: `America/New_York`). Outside this window the form shows a live countdown to next Friday opening.

2. **Dual Cap — whichever hits first closes the form:**
   - Maximum **100 cinnamon rolls** sold per weekend
   - Maximum **50 customer orders** per weekend

3. **Payment:** Square card payments ONLY. Card is charged immediately on submission. No manual verification. No Zelle. No PayPal.

4. **Cap enforcement is ALWAYS server-side.** The frontend reflects state but never enforces it. A direct POST to the API outside the window or after cap must be rejected.

5. **Atomic transactions.** Cap check and order insert must happen in a single DB transaction with `FOR UPDATE` row locking to prevent race conditions.

---

## BRAND

- **Business:** Bachata Bakery
- **Palette:** Deep chocolate brown `#3B1A08`, warm cream `#F5EFE6`, teal `#1A9E8F`, coral red `#E52521`, golden amber `#F4A228`
- **Fonts:** Playfair Display (headings), DM Sans (body) — both from Google Fonts
- **Aesthetic:** Warm artisanal, Dominican cultural identity, parchment textures, bold script energy

---

## TECH STACK

| Layer | Technology |
|---|---|
| Frontend | HTML5 / Vanilla JS / CSS3 |
| Payment UI | Square Web Payments SDK (CDN) |
| Backend | PHP 8.x |
| Database | MySQL 8.x |
| Payments | Square Payments API (REST) |
| Email | PHPMailer + Hostinger SMTP |
| Admin | PHP session-protected dashboard |
| Hosting | Hostinger |

---

## FILE STRUCTURE TO BUILD

```
bachata-bakery-orders/
├── .env                        # Secrets — never committed
├── .env.example                # Template for .env
├── .htaccess                   # Block .env + /api/helpers/ from browser access
├── schema.sql                  # Full DB schema
│
├── index.html                  # Customer-facing order form
├── assets/
│   ├── css/
│   │   └── styles.css          # All styles — mobile-first
│   └── js/
│       └── form.js             # All frontend JS — vanilla
│
├── api/
│   ├── helpers/
│   │   ├── db.php              # PDO connection singleton
│   │   ├── time_gate.php       # isFormOpen() function
│   │   ├── cap.php             # Cap check helpers
│   │   └── mailer.php          # PHPMailer wrapper
│   ├── status.php              # GET — open/closed state + cap counters
│   ├── order.php               # POST — submit order (atomic cap + Square charge)
│   └── webhook/
│       └── square.php          # POST — Square webhook receiver
│
├── admin/
│   ├── index.php               # Login redirect
│   ├── login.php               # Admin login form
│   ├── dashboard.php           # Main owner dashboard
│   ├── logout.php
│   └── actions/
│       ├── force-close.php     # Toggle force close flag
│       ├── cap-override.php    # Update rolls_max / orders_max
│       └── export-csv.php      # Export orders to CSV
│
└── emails/
    ├── confirmation.html       # Customer order confirmation template
    └── owner-alert.html        # Owner new order notification template
```

---

## DATABASE SCHEMA

### Table: `orders`
```sql
CREATE TABLE orders (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    order_ref         VARCHAR(12) NOT NULL UNIQUE,
    window_id         DATE NOT NULL,
    customer_name     VARCHAR(120) NOT NULL,
    customer_email    VARCHAR(180) NOT NULL,
    customer_phone    VARCHAR(20),
    quantity          TINYINT UNSIGNED NOT NULL,
    product_variant   VARCHAR(80),
    pickup_date       DATE,
    special_notes     TEXT,
    payment_status    ENUM('paid', 'failed') NOT NULL DEFAULT 'failed',
    square_payment_id VARCHAR(80),
    amount_cents      INT UNSIGNED NOT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_window  (window_id),
    INDEX idx_status  (payment_status)
);
```

### Table: `order_caps`
```sql
CREATE TABLE order_caps (
    window_id      DATE PRIMARY KEY,
    rolls_sold     TINYINT UNSIGNED DEFAULT 0,
    orders_placed  TINYINT UNSIGNED DEFAULT 0,
    rolls_max      TINYINT UNSIGNED DEFAULT 100,
    orders_max     TINYINT UNSIGNED DEFAULT 50,
    force_closed   TINYINT(1) DEFAULT 0,
    updated_at     DATETIME ON UPDATE CURRENT_TIMESTAMP
);
```

---

## API CONTRACT

### GET /api/status.php
Returns current form state. Polled by frontend every 60 seconds.

**Response:**
```json
{
  "open": true,
  "next_open": "2026-04-17T00:00:00-05:00",
  "rolls_remaining": 87,
  "orders_remaining": 44,
  "force_closed": false
}
```

### POST /api/order.php
Submit an order. Must validate time gate, cap, charge Square, and create order atomically.

**Request body:**
```json
{
  "nonce": "cnon:card-nonce-ok",
  "name": "Maria Santos",
  "email": "maria@email.com",
  "phone": "302-555-0101",
  "quantity": 12,
  "variant": "Classic Glazed",
  "pickup_date": "2026-04-12",
  "notes": "Extra icing please",
  "amount_cents": 2400
}
```

**Success response:**
```json
{ "success": true, "order_ref": "BB-20260411-0007", "message": "Order placed! Check your email." }
```

**Error response:**
```json
{ "success": false, "error_code": "sold_out_orders", "message": "All order slots are filled!" }
```

**Error codes:** `form_closed` | `sold_out_rolls` | `sold_out_orders` | `card_declined` | `invalid_input` | `server_error`

### POST /api/webhook/square.php
Receives Square webhook events. Must validate `x-square-hmacsha256-signature` before processing.
Handles: `payment.completed`, `payment.failed`

---

## SQUARE INTEGRATION NOTES

- **Frontend:** Square Web Payments SDK loaded from CDN. Initialize with `APP_ID` and `LOCATION_ID` from `.env`. Mount card to `#card-container` div.
- **Backend:** Charge via `POST https://connect.squareup.com/v2/payments` with Bearer token auth.
- **Idempotency key:** Generate per session (e.g., `uniqid('bb_', true)`) to prevent double charges on retry.
- **Sandbox:** Use `https://sandbox.web.squarecdn.com/v1/square.js` during development. Switch to `https://web.squarecdn.com/v1/square.js` for production.
- **Webhook signature key** stored in `.env` as `SQUARE_WEBHOOK_SIG_KEY`.

**.env structure:**
```
APP_ENV=sandbox
SQUARE_APP_ID=sandbox-sq0idb-...
SQUARE_LOCATION_ID=...
SQUARE_ACCESS_TOKEN=EAAAl...
SQUARE_WEBHOOK_SIG_KEY=...
DB_HOST=localhost
DB_NAME=bachata_bakery
DB_USER=
DB_PASS=
SMTP_HOST=smtp.hostinger.com
SMTP_USER=orders@bachatbakery.com
SMTP_PASS=
SMTP_PORT=465
OWNER_EMAIL=owner@bachatabakery.com
BAKERY_TIMEZONE=America/New_York
```

---

## ADMIN DASHBOARD REQUIREMENTS

- Password protected via PHP session (no framework auth needed — simple session-based login)
- Displays: rolls sold vs. cap, orders placed vs. cap, weekend revenue total
- Full order table: sortable by date, searchable by name/email, shows payment status
- **Force Close toggle** — sets `force_closed = 1` in `order_caps` for current window
- **Cap override fields** — editable `rolls_max` and `orders_max` per weekend
- **CSV export** — all orders for current weekend window
- Styled to match Bachata Bakery brand palette

---

## EMAIL REQUIREMENTS

### Customer confirmation email (sent on successful order):
- Order reference code (large, prominent)
- Items ordered + quantity
- Amount charged
- Pickup date (if selected)
- Bakery contact info
- Branded HTML template using bakery colors

### Owner notification email (sent on every new order):
- Customer name, email, phone
- Order ref, quantity, variant
- Amount charged
- Running totals: rolls sold / orders placed this weekend

---

## QA CHECKLIST (verify each before marking phase complete)

### Time Gate
- [ ] Form closed Thursday 11:58 PM; shows countdown
- [ ] Form auto-opens Friday 12:01 AM without reload
- [ ] Direct POST outside window returns `form_closed` error

### Cap Enforcement
- [ ] 50th order accepted; form closes on next status.php poll
- [ ] 51st order rejected; no charge attempted
- [ ] 94 rolls + order of 7 = rejected; 94 rolls + order of 6 = accepted
- [ ] Two simultaneous final-slot submissions: one succeeds, one fails

### Square Payments
- [ ] Visa `4111 1111 1111 1111` → order created, email received
- [ ] Declined card `4000 0000 0000 0002` → error shown, no order record
- [ ] Double-click submit → single charge only
- [ ] Webhook fires and reconciles orphaned charge scenario

---

## BUILD ORDER — PHASE BY PHASE

Work through these in order. Do not skip ahead. Complete and test each phase before moving on.

1. **Phase 1:** `schema.sql` + `api/helpers/db.php` + `.env.example` + `.htaccess`
2. **Phase 2:** `api/helpers/time_gate.php` + `api/helpers/cap.php` + `api/status.php`
3. **Phase 3:** `index.html` + `assets/css/styles.css` + `assets/js/form.js` (UI only, no payment yet)
4. **Phase 4:** Square Web Payments SDK in frontend (card mount + tokenization)
5. **Phase 5:** `api/order.php` (atomic cap check + Square charge + order insert)
6. **Phase 6:** `api/webhook/square.php` (signature validation + reconciliation)
7. **Phase 7:** `api/helpers/mailer.php` + email templates
8. **Phase 8:** `admin/` dashboard (login, dashboard, actions)
9. **Phase 9:** Full QA pass against checklist above
10. **Phase 10:** Production deployment prep (env swap, SSL check, live $0.01 test)

---

## START COMMAND

Begin with **Phase 1**. Create `schema.sql`, `api/helpers/db.php`, `.env.example`, and `.htaccess`. 

When each file is created, confirm what was built and what the next step is before proceeding.
