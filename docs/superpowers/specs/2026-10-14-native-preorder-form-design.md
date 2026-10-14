# Native Preorder Form (Square) — Demo Design

Date: 2026-10-14
Status: Approved for planning

## Goal

Replace the embedded Jotform preorder (form `253028973146057`) with a native,
on-site order form that takes payment through Square. This spec covers a
**demo page** running against the Square **sandbox**; the live `/order/` page
is not changed.

## Decisions

| Topic | Decision |
|---|---|
| Server | Extend the existing PHP backend in `cinnamon-roll-order-form/` (Hostinger) |
| Payment UI | Square Web Payments SDK embedded on the page |
| Payment methods | Card, Apple Pay, Google Pay, Cash App Pay |
| Scope | Square only — no database, no sell-out caps, no custom emails |
| Menu source | Hardcoded catalog file in the repo, validated server-side |
| Environment | Square sandbox (`APP_ENV=sandbox` in existing `.env`) |

## Page: `/order-demo/`

New page `order-demo/index.html`, reusing the site nav, footer, and
`styles.css`. It has `<meta name="robots" content="noindex, nofollow">`, is
not linked from any page, and is not added to `sitemap.xml`.

Sections, top to bottom:

1. **Contact** — first name, last name, email, phone (all required; same
   fields and autocomplete attributes as the current presale form).
2. **Pickup** — location radio:
   - Middletown, DE — Sundays 1:00–4:00 PM
   - Artisan Exchange, West Chester, PA — Saturdays 10:00 AM–1:00 PM

   A time `<select>` lists 30-minute slots inside the chosen location's hours
   (Middletown: 1:00, 1:30, 2:00, 2:30, 3:00, 3:30 PM; West Chester: 10:00,
   10:30, 11:00, 11:30 AM, 12:00, 12:30 PM). The computed pickup date is shown
   next to the location (e.g. "Saturday, Oct 17").
3. **Menu** — three groups, quantity stepper 0–10 per item, line subtotal,
   running order total.
4. **Payment** — wallet buttons (Apple Pay, Google Pay, Cash App Pay; each only
   rendered when the SDK reports the method is available on the device), the
   Square card form, and a "Pay $X.XX" button. Pay is disabled while the total
   is $0 or a request is in flight.
5. **Confirmation** — replaces the form on success: items, total, pickup
   location/date/time, and a link to the Square receipt.

When the preorder window is closed, the page shows the same closed callout as
`/order/` by reusing `assets/js/preorder-window.js` (`#presale-form`,
`#presale-closed`, `#jump-btn-wrap`, `#presale-open-callout` IDs).

## Catalog

`cinnamon-roll-order-form/api/helpers/catalog.php` returns the menu. It is the
only source of prices. `MAX_QTY_PER_ITEM = 10`.

| id | Category | Name | Price |
|---|---|---|---|
| `cookie-preset` | Preset Sugar Cookies | Sugar Cookie | $7.00 |
| `cookie-gift` | Preset Sugar Cookies | Sugar Cookie with Gift Tag | $8.00 |
| `roll-clasico` | Cinnamon Rolls | El Clásico | $8.00 |
| `roll-dominicana` | Cinnamon Rolls | La Dominicana | $8.00 |
| `roll-cafe` | Cinnamon Rolls | Café con Leche | $8.00 |
| `roll-dulce` | Cinnamon Rolls | Dulce | $8.00 |
| `roll-choco-banana` | Cinnamon Rolls | Choco Banana | $8.00 |
| `tl-classic` | Tres Leches Cakes | Classic | $8.00 |
| `tl-guava` | Tres Leches Cakes | Guava | $8.00 |
| `tl-coffee` | Tres Leches Cakes | Coffee | $8.00 |
| `tl-passion` | Tres Leches Cakes | Passion Fruit | $8.00 |

Each item also carries the one-line description used on `/order/`.

## Pickup date rule

Orders are accepted Sunday 00:00 through Wednesday 23:59:59 (bakery timezone,
`BAKERY_TIMEZONE`, default `America/New_York`). Pickup is the weekend right
after that Wednesday close:

- West Chester → the Saturday after the current order week's Wednesday
- Middletown → the Sunday after that Saturday

Example: an order on Wed Oct 14, 2026 picks up Sat Oct 17 (West Chester) or
Sun Oct 18 (Middletown). The server computes these dates; the page displays
what the server returns.

## Server endpoints

All under `cinnamon-roll-order-form/api/`. `helpers/` is already blocked from
HTTP by `.htaccess`.

### `GET menu.php`

Returns the catalog and pickup options:

```json
{
  "open": true,
  "max_qty_per_item": 10,
  "items": [{ "id": "roll-clasico", "category": "Cinnamon Rolls", "name": "El Clásico", "description": "…", "price_cents": 800 }],
  "pickup": [
    { "id": "middletown",   "label": "Middletown, DE",                     "date": "2026-10-18", "date_label": "Sunday, Oct 18",   "slots": ["13:00","13:30","14:00","14:30","15:00","15:30"] },
    { "id": "westchester",  "label": "Artisan Exchange, West Chester, PA", "date": "2026-10-17", "date_label": "Saturday, Oct 17", "slots": ["10:00","10:30","11:00","11:30","12:00","12:30"] }
  ]
}
```

### `GET config.php` (existing, unchanged)

Returns `square_app_id`, `square_location_id`, `app_env`.

### `POST checkout.php`

Request (`application/json`):

```json
{
  "token": "cnon:…",
  "idempotency_key": "uuid-v4",
  "contact": { "first": "Ana", "last": "Diaz", "email": "ana@example.com", "phone": "(302) 555-0100" },
  "pickup": { "location": "westchester", "time": "10:30" },
  "items": [{ "id": "roll-clasico", "qty": 2 }, { "id": "tl-guava", "qty": 1 }],
  "amount_cents": 2400
}
```

Steps:

1. Require `POST` and JSON content type.
2. `isFormOpen()` from `helpers/time_gate.php`; closed → `403 form_closed`.
3. Validate:
   - `token` non-empty string; `idempotency_key` matches UUID v4 format.
   - first/last 1–60 chars; email valid, ≤180 chars; phone 7–20 chars of
     digits, spaces, `()+-.`.
   - `pickup.location` is a known id; `pickup.time` is one of that location's slots.
   - `items`: 1–11 entries, every `id` in the catalog, no duplicate ids, `qty`
     integer 1–10.
   - Recompute total from the catalog; must equal `amount_cents`.
   - Any failure → `422 invalid_input` with a readable message.
4. Square `POST /v2/orders` (idempotency key = `"{key}-order"`):
   - `location_id`, `reference_id` = short ref `BB-YYYYMMDD-XXXX` (random
     4 hex chars),
   - `line_items`: `name`, `quantity` (string), `base_price_money` from catalog,
   - `fulfillments`: one `PICKUP` with `recipient` (display name, email,
     phone), `pickup_at` = pickup date + slot in bakery timezone (RFC 3339),
     `note` = location label.
5. Verify Square's `total_money.amount` equals our computed total; mismatch →
   `500 server_error` (logged).
6. Square `POST /v2/payments` (idempotency key = `"{key}-pay"`): `source_id`
   = token, `order_id`, `amount_money`, `location_id`, `reference_id`,
   `buyer_email_address`, `note` = "Pickup: {location} {date} {time} — {name}, {phone}"
   (truncated to 500 chars).
7. Success → `200 { success: true, reference_id, order_id, receipt_url, total_cents, pickup: {…}, items: [{name, qty, line_cents}] }`.
   Payment error → `402 card_declined` with the friendly message mapping
   already used in `order.php`.

Square API version header and base URLs match `order.php`
(`connect.squareupsandbox.com` when `APP_ENV` ≠ `production`). Square calls
use cURL with 10s connect / 15s total timeouts. Access token never leaves the
server.

## Front end: `assets/js/order-native.js`

- On load: fetch `menu.php` and `config.php` in parallel, render menu and
  pickup options, load the Square SDK for `app_env`, attach card form.
- Build one `paymentRequest` (`countryCode: 'US'`, `currencyCode: 'USD'`);
  call `paymentRequest.update({ total })` whenever quantities change.
- Wallets: `payments.applePay(req)`, `payments.googlePay(req)`,
  `payments.cashAppPay(req, { redirectURL: location.href, referenceId })`.
  Each is wrapped in try/catch; unsupported methods are simply not shown.
- Card/Apple Pay/Google Pay: on click → validate form → `tokenize()` → submit.
- Cash App Pay: on its `ontokenization` event → validate form → submit; if the
  form is invalid, show errors and do not submit (token expires unused).
- Each payment attempt gets a fresh `crypto.randomUUID()` idempotency key;
  the button is disabled while a request is in flight.
- API base resolves the same way as `preorder-window.js` (`/cinnamon-roll-order-form`,
  honouring `window.BACHATA_API_BASE`).
- Errors render in an `aria-live="polite"` region next to the Pay button;
  field errors use `aria-invalid` + inline messages.

## Error handling

| Code | HTTP | Shown to customer |
|---|---|---|
| `form_closed` | 403 | "Orders are closed right now — the window reopens Sunday." |
| `invalid_input` | 422 | Server's message (specific field problem) |
| `card_declined` | 402 | Friendly decline message (card declined / expired / CVV / ZIP / funds) |
| `server_error` | 500 | "Something went wrong on our end. You have not been charged." |

A failed payment leaves an unpaid Square order; unpaid API orders do not
appear in Order Manager. Retrying uses a new idempotency key.

## Testing

- `php -l` on every new PHP file.
- Local: install PHP (`winget install PHP.PHP.8.3`, with the `curl` extension
  enabled), run `php -S localhost:8000` from the repo root, open
  `http://localhost:8000/order-demo/`.
- Sandbox card `4111 1111 1111 1111` (any future expiry, CVV `111`, ZIP
  `12345`) → success; confirm order + pickup fulfillment in the sandbox Seller
  Dashboard.
- Sandbox decline card `4000 0000 0000 0002` → `card_declined` message.
- Tamper test: POST with wrong `amount_cents` → `422`.
- Closed-window test: temporarily force `isFormOpen()` false → `403` and
  closed callout shown.
- Google Pay and Cash App Pay sandbox flows in Chrome.
- Apple Pay: only after deploying to HTTPS and registering the domain in the
  Square Developer Dashboard (upload
  `/.well-known/apple-developer-merchantid-domain-association`).

## Out of scope

Database writes, weekly sell-out caps, owner/customer emails from our server,
admin dashboard, the Custom Cookie Request tab, hCaptcha, and replacing the
live `/order/` page.
