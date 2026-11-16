# Native Preorder — Go-Live Hardening Design

Date: 2026-10-14
Builds on: `2026-10-14-native-preorder-form-design.md`
Status: Approved by standing instruction (build after spec)

## Goal

Make the native Square preorder form safe to run with real money:
card-testing protection, a server-enforced pause/vacation switch, weekly
sell-out limits, and the tooling + checklist to deploy it to Hostinger.

## 1. Data layer (MySQL, required in production)

The backend has never been deployed, so `schema.sql` is updated in place
(no migration). Changes:

- `orders`
  - `order_ref` → `VARCHAR(20)` (current 12 cannot hold `BB-YYYYMMDD-XXXX`).
  - `quantity` → `SMALLINT UNSIGNED` — total items in the order.
  - `product_variant` → `VARCHAR(255)` — readable summary, e.g. `2× El Clásico, 1× Guava Tres Leches`.
  - add `items_json TEXT` (lines as JSON), `pickup_location VARCHAR(60)`,
    `pickup_time TIME`, `square_order_id VARCHAR(80)`.
- `order_caps`: counters/limits → `SMALLINT UNSIGNED`. `rolls_sold` /
  `rolls_max` now mean **total items of any product** for the window
  (column names kept so admin + webhook keep working; UI says "Items").
  Defaults: 100 items, 50 orders.
- new `store_settings (setting_key VARCHAR(40) PK, setting_value TEXT, updated_at)`
  — holds `paused_until` (Y-m-d) and `pause_message`.
- new `checkout_attempts (id, ip_hash CHAR(64), outcome ENUM('attempt','declined','paid'), created_at)`
  with index `(ip_hash, created_at)`.

## 2. Order window id

`window_id` = the **Wednesday that closes the order week** (Y-m-d), from
`orderWeekWednesday()` in `catalog.php`. `getCurrentWindowId()` in
`time_gate.php` returns this. This fixes the old Friday key that split one
Sun–Wed week across two windows. The legacy `api/order.php` (old $6 roll
form) depends on the Friday key; it is superseded, left in the repo, and
excluded from deployment.

## 3. Store status — `api/helpers/store.php`

`storeStatus(PDO $pdo, DateTimeImmutable $now): array` is the single source
of truth used by `status.php`, `menu.php`, and `checkout.php`:

```
open, closed_reason (null|'time_gate'|'vacation'|'sold_out'|'force_closed'),
window_id, closes_at, reopens_at, items_remaining, orders_remaining, message
```

Precedence: vacation (paused_until > today) → time gate (Thu–Sat) →
force_closed → sold out (items or orders exhausted). `reopens_at` for
vacation is `paused_until 00:00`; otherwise next Sunday 00:00.

## 4. Pause / vacation switch

- Admin dashboard card: "Pause orders until [date]" + optional message, and
  "Resume now". POST → `admin/actions/pause.php` (CSRF-protected).
- `status.php` returns `closed_reason: 'vacation'` and `reopens_at`;
  `preorder-window.js` labels it from `reopens_at` (hardcoded
  `VACATION_REOPEN` removed).
- `order-native.js` uses `menu.php`'s `open`/`closed_reason`/`message` to show
  the closed callout itself.

## 5. Sell-out limits in checkout

`checkout.php` follows the proven `order.php` pattern:

1. Rate limit + captcha (§6).
2. `storeStatus()` must be open.
3. Validate (unchanged).
4. Transaction: `ensureCapRowExists` → `SELECT … FOR UPDATE` → reject if
   force-closed (`403 form_closed`), orders full or not enough items
   (`409 sold_out`, message says how many items remain) → insert order
   `pending` → increment counters → commit.
5. Square CreateOrder + CreatePayment (unchanged bodies).
6. Success → order `paid` with Square order/payment ids.
   Any Square failure → order `failed`, counters decremented.

`menu.php` returns `items_remaining`; the page stops the "+" buttons once the
cart reaches it and shows "Only N left this week" when ≤ 15.

## 6. Card-testing protection

- **hCaptcha** checkbox above the Pay button. `config.php` returns
  `hcaptcha_site_key`. Every payment method (card and wallets) requires a
  completed captcha; the token is sent as `captcha_token` and verified with
  `https://api.hcaptcha.com/siteverify` (secret `HCAPTCHA_SECRET`). The widget
  resets after every attempt.
  - `.env`: `HCAPTCHA_SITE_KEY`, `HCAPTCHA_SECRET`. Local/sandbox uses
    hCaptcha's public test keys. If the secret is empty: sandbox skips the
    check; production refuses checkout (`500 server_error`, logged).
- **Rate limits** (per hashed client IP, from `checkout_attempts`):
  - more than `RATE_LIMIT_ATTEMPTS` (default 8) attempts in 15 min → `429 rate_limited`
  - `RATE_LIMIT_DECLINES` (default 3) declines in 60 min → `429 rate_limited`
  - Rows older than 2 days are purged opportunistically.
  - Client IP is `REMOTE_ADDR`. Must be checked on Hostinger (CDN may
    proxy); recorded as a go-live checklist item.

## 7. Webhook fix

Square sends `payment.created` / `payment.updated`, not
`payment.completed` / `payment.failed`. The handler routes both real event
types by `data.object.payment.status`: `COMPLETED` → completed handler,
`FAILED` / `CANCELED` → failed handler. Reconciliation logic is unchanged.

## 8. Admin dashboard

- All redirects/links become relative (backend lives under
  `/cinnamon-roll-order-form/`, absolute `/admin/...` links 404).
- Window selector: dropdown of windows (current + any with cap rows).
  Default = earliest window whose Sunday pickup is today or later, so on
  pickup weekend the baker sees that weekend's orders.
- Stats say "Items sold"; order table shows item summary, pickup location
  and time; CSV export adds Items, Pickup Location, Pickup Time, Square Order ID.
- Pause card (§4).

## 9. Deployment tooling

- `scripts/deploy.ps1`: allowlist + defaults gain `order-demo/index.html`,
  `assets/js/order-native.js`, and
  `.well-known/apple-developer-merchantid-domain-association`; page check
  gains `order-demo/`.
- New `scripts/deploy-backend.ps1`: uploads an explicit allowlist of backend
  files to `public_html/cinnamon-roll-order-form/` (or `dev/…`). Never uploads
  `order.php`, the old `index.html`/`form.js`, tests, docs, or `storage/`.
  `.env` is only uploaded when `-EnvFile <path>` is passed explicitly.
- Backend `.htaccess`: HTTPS redirect on, `tests/` denied.

## 10. Go-live checklist

`docs/go-live-checklist.md`: create DB (Hostinger) and run `schema.sql`;
production `.env` (Square prod keys, hCaptcha secret, DB, `APP_ENV=production`,
strong `ADMIN_PASS`); deploy backend + front end; verify `REMOTE_ADDR`;
register Square webhook (`payment.created`, `payment.updated`) and set
`SQUARE_WEBHOOK_SIG_KEY`/`WEBHOOK_URL`; Apple Pay domain file; set caps;
live test order then refund; swap `/order/` to the native form.

## Testing

- CLI tests (`tests/run.php`): store-status precedence, window id, rate-limit
  thresholds (pure helpers), webhook routing.
- Local integration: portable MariaDB + `php -S`; sandbox checkout succeeds
  and decrements caps; sold-out → 409; pause → 403 and closed UI; decline →
  counters restored; rate limit → 429; captcha with hCaptcha test keys.
- Admin: login, window selector, pause/resume, cap override, CSV.

## Out of scope

Order emails (owner alert / customer confirmation), Square Catalog sync,
per-product limits.
