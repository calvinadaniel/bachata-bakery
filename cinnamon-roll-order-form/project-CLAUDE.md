# CLAUDE.md — Bachata Bakery · Cinnamon Roll Order System
# Place this file at: bachata-bakery-orders/CLAUDE.md
# Project-specific context. Extends ~/.claude/CLAUDE.md — don't repeat global rules here.

---

## PROJECT IDENTITY

- **Client:** Bachata Bakery — small Dominican baking business
- **Project:** Time-gated, cap-enforced cinnamon roll pre-order form
- **Developer:** Calvin Daniel
- **Hosting:** Hostinger (existing infrastructure)
- **Payment:** Square Payments API — card only, charged immediately on submission

---

## BRAND

### Color Palette
```css
:root {
  --brown:  #3B1A08;   /* Primary — deep chocolate brown */
  --cream:  #F5EFE6;   /* Background — warm cream */
  --teal:   #1A9E8F;   /* Accent — teal green */
  --coral:  #E52521;   /* Alert / error — coral red */
  --amber:  #F4A228;   /* Highlight — golden amber */
  --white:  #FFFFFF;

  /* Grays derived from brand */
  --gray-100: #F5F0EA;
  --gray-200: #EDE7DE;
  --gray-400: #C4B5A5;
  --gray-500: #9E8C79;
  --gray-700: #5C4A38;
}
```

### Typography
```css
--font-display: 'Playfair Display', Georgia, serif;   /* Headings */
--font-body:    'DM Sans', system-ui, sans-serif;      /* Body / UI */
--font-mono:    'DM Mono', 'Courier New', monospace;   /* Code / labels */
```
Google Fonts import:
```html
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
```

### Aesthetic Direction
- Warm, artisanal — parchment textures, rich browns, soft glows
- Dominican cultural energy — bold, celebratory, not generic
- Typography: dramatic size contrast between Playfair headings and DM Sans body
- Backgrounds: radial gradients with amber and teal glows on dark surfaces
- No cold grays, no blue-tinted neutrals — everything has warmth

---

## BUSINESS RULES (ENFORCE ALWAYS)

1. **Time Gate:** Form open Friday 12:00 AM – Sunday 11:59 PM (`America/New_York`). Always server-enforced.
2. **Cap A:** Maximum 100 cinnamon rolls per weekend window.
3. **Cap B:** Maximum 50 customer orders per weekend window.
4. **Form closes when either cap is hit** — whichever comes first.
5. **Payment:** Square only. Card charged immediately. No holds. No manual steps.
6. **Cap check is ALWAYS atomic** — `FOR UPDATE` row lock, single transaction with order insert.
7. **Frontend never enforces caps or time gate** — it reflects state from `/api/status.php` only.

---

## DATABASE

### Tables
```sql
-- orders: one row per successful customer order
CREATE TABLE orders (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    order_ref         VARCHAR(12) NOT NULL UNIQUE,   -- e.g. BB-20260411-0007
    window_id         DATE NOT NULL,                 -- Friday date of the weekend
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

-- order_caps: one row per weekend window
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

## FILE STRUCTURE

```
bachata-bakery-orders/
├── .env                        # Secrets — never committed
├── .env.example                # Placeholder template
├── .htaccess                   # Block .env + /api/helpers/ from direct access
├── schema.sql
├── CLAUDE.md                   # This file
│
├── index.html                  # Customer-facing order form
├── assets/
│   ├── css/styles.css
│   └── js/form.js
│
├── api/
│   ├── helpers/
│   │   ├── db.php              # PDO singleton
│   │   ├── time_gate.php       # isFormOpen() — server timezone enforced
│   │   ├── cap.php             # Cap check helpers
│   │   └── mailer.php          # PHPMailer wrapper
│   ├── status.php              # GET — returns open state + remaining caps
│   ├── order.php               # POST — atomic cap check + Square charge + insert
│   └── webhook/
│       └── square.php          # POST — Square webhook, HMAC-SHA256 validation
│
├── admin/
│   ├── index.php               # Redirects to login
│   ├── login.php
│   ├── dashboard.php           # Cap counters, order table, controls
│   ├── logout.php
│   └── actions/
│       ├── force-close.php
│       ├── cap-override.php
│       └── export-csv.php
│
└── emails/
    ├── confirmation.html       # Customer receipt template
    └── owner-alert.html        # Owner new order notification
```

---

## API CONTRACTS

### GET /api/status.php
```json
{
  "open": true,
  "next_open": "2026-04-17T00:00:00-05:00",
  "rolls_remaining": 87,
  "orders_remaining": 44,
  "force_closed": false
}
```

### POST /api/order.php — Request
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

### POST /api/order.php — Responses
```json
// Success
{ "success": true, "order_ref": "BB-20260411-0007", "message": "Order placed! Check your email." }

// Error
{ "success": false, "error_code": "sold_out_orders", "message": "All order slots are filled!" }
```

**Error codes:** `form_closed` | `sold_out_rolls` | `sold_out_orders` | `card_declined` | `invalid_input` | `server_error`

---

## SQUARE INTEGRATION

### Frontend (SDK)
- Sandbox: `https://sandbox.web.squarecdn.com/v1/square.js`
- Production: `https://web.squarecdn.com/v1/square.js`
- Mount card input to `#card-container`
- Disable submit button on first click — re-enable only on error response
- Pass `result.token` (nonce) to backend on tokenization success

### Backend (PHP cURL)
- Endpoint: `POST https://connect.squareup.com/v2/payments`
- Auth: `Authorization: Bearer {SQUARE_ACCESS_TOKEN}`
- Always generate idempotency key: `uniqid('bb_', true)`
- On `COMPLETED` response: commit transaction, save `square_payment_id`
- On any other status: rollback, return `card_declined` error

### Webhook
- Validate `x-square-hmacsha256-signature` header before any processing
- Handle: `payment.completed`, `payment.failed`
- Reconcile orphaned charges (PHP timeout edge case)

### .env Keys
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
SMTP_USER=orders@bachatabakery.com
SMTP_PASS=
SMTP_PORT=465
OWNER_EMAIL=owner@bachatabakery.com
BAKERY_TIMEZONE=America/New_York
```

---

## FRONTEND BEHAVIOR

- On load: call `GET /api/status.php` — show countdown or form based on `open` field
- Poll `status.php` every 60 seconds — auto-unlock form at Friday midnight without reload
- Show live `rolls_remaining` and `orders_remaining` counters in the form
- On sold-out: hide form, show "We'll be back next Friday" state
- On Square decline: show plain-English error, re-enable submit button
- All form fields validated client-side before Square tokenization runs

---

## ADMIN DASHBOARD

- Session-based PHP login — no framework auth
- Shows: rolls sold / cap, orders placed / cap, weekend revenue total
- Full order table: searchable by name/email, sortable by date, shows payment status
- Force Close toggle — sets `force_closed = 1` for current `window_id`
- Cap override inputs — editable `rolls_max` and `orders_max`
- CSV export of all orders for current weekend
- Styled with Bachata Bakery brand palette (dark brown header, cream background)

---

## BUILD ORDER

Complete each phase fully before starting the next. Confirm completion before proceeding.

| Phase | Files |
|---|---|
| 1 — Foundation | `schema.sql`, `api/helpers/db.php`, `.env.example`, `.htaccess` |
| 2 — Backend Core | `api/helpers/time_gate.php`, `api/helpers/cap.php`, `api/status.php` |
| 3 — Frontend UI | `index.html`, `assets/css/styles.css`, `assets/js/form.js` |
| 4 — Square Frontend | Square SDK in `form.js` — card mount + tokenization |
| 5 — Order API | `api/order.php` — atomic cap + Square charge + insert |
| 6 — Webhook | `api/webhook/square.php` — signature validation + reconciliation |
| 7 — Email | `api/helpers/mailer.php`, `emails/confirmation.html`, `emails/owner-alert.html` |
| 8 — Admin | Full `admin/` directory |
| 9 — QA | All test cases below |
| 10 — Deploy | Env swap, SSL check, $0.01 live charge, webhook registration |

---

## QA CHECKLIST

### Time Gate
- [ ] Thursday 11:58 PM — form closed, countdown showing
- [ ] Friday 12:01 AM — form auto-opens without reload
- [ ] Direct POST outside window — backend returns `form_closed`

### Cap Enforcement
- [ ] 50th order accepted; form closes on next status poll
- [ ] 51st order rejected with `sold_out_orders`; no charge
- [ ] 94 rolls + order of 7 → rejected; 94 rolls + order of 6 → accepted
- [ ] Two simultaneous final-slot submissions → one succeeds, one fails

### Square Payments
- [ ] `4111 1111 1111 1111` → order created, email received
- [ ] `4000 0000 0000 0002` → error shown, no order record created
- [ ] Double-click submit → single charge only
- [ ] PHP timeout after charge → webhook reconciles order

### Pre-Launch
- [ ] SSL active on Hostinger domain
- [ ] Square Production credentials in `.env`
- [ ] Live $0.01 charge + immediate refund
- [ ] Webhook endpoint registered in Square dashboard
- [ ] Confirmation email passes spam filter
- [ ] `.env` returns 403 via browser
