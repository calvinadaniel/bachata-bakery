# Native Preorder Form (Square) Implementation Plan

> **For agentic workers:** Execute task-by-task (superpowers:executing-plans). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `/order-demo/` page that sells the 11 preorder items and takes card, Apple Pay, Google Pay, and Cash App Pay through Square (sandbox), replacing the Jotform flow.

**Architecture:** Static page + vanilla JS using the Square Web Payments SDK for tokenization. PHP endpoints in `cinnamon-roll-order-form/api/` serve the menu and perform checkout (Square Orders API → Payments API). Pure logic (catalog, pickup dates, validation, Square request bodies) lives in `api/helpers/` so it can be tested from the CLI without network calls.

**Tech Stack:** PHP 8.3 (cURL), vanilla JS (ES2020), Square Web Payments SDK v1, Square REST API `2024-01-18`.

Spec: `docs/superpowers/specs/2026-10-14-native-preorder-form-design.md`

## Global Constraints

- Square sandbox when `APP_ENV` ≠ `production`; access token never sent to the browser.
- Prices exist only in `api/helpers/catalog.php`; server recomputes totals.
- `MAX_QTY_PER_ITEM = 10`; orders accepted Sun 00:00 – Wed 23:59:59 bakery time.
- Pickup: West Chester = Saturday after the order week's Wednesday (10:00–12:30 slots); Middletown = the following Sunday (13:00–15:30 slots).
- Demo page is `noindex`, unlinked, not in `sitemap.xml`. Live `/order/` unchanged.
- No DB access in any new code path.

## File Structure

| File | Responsibility |
|---|---|
| `cinnamon-roll-order-form/api/helpers/catalog.php` | Menu items, pickup locations, pickup-date math, menu payload |
| `cinnamon-roll-order-form/api/helpers/checkout_lib.php` | Request validation, Square order/payment body builders, reference ids |
| `cinnamon-roll-order-form/api/helpers/square.php` | cURL wrapper for Square REST, decline-message mapping |
| `cinnamon-roll-order-form/api/menu.php` | `GET` → menu JSON |
| `cinnamon-roll-order-form/api/checkout.php` | `POST` → validate, create order, take payment |
| `cinnamon-roll-order-form/tests/run.php` | CLI assertions for catalog + checkout_lib |
| `order-demo/index.html` | Demo page markup |
| `assets/js/order-native.js` | Menu rendering, totals, Square SDK, submission |
| `styles.css` | Stepper, order summary, payment area styles (appended) |

---

### Task 1: Catalog and menu endpoint

**Files:** Create `api/helpers/catalog.php`, `api/menu.php`, `tests/run.php`.

**Interfaces — produces:**
- `const MAX_QTY_PER_ITEM = 10;`
- `catalogItems(): array` — list of `['id','category','name','description','price_cents']`
- `catalogById(): array<string,array>`
- `pickupLocations(): array<string,array{id,label,weekday,slots:string[]}>`
- `pickupOptions(DateTimeImmutable $now): array` — list of `['id','label','date'(Y-m-d),'date_label','slots']`
- `isOpenAt(DateTimeImmutable $now): bool`
- `menuPayload(DateTimeImmutable $now): array` — `['open','max_qty_per_item','items','pickup']`

- [ ] Write `tests/run.php` asserting: 11 items, all `price_cents` ∈ {700, 800}; on Wed 2026-10-14 12:00 ET → westchester `2026-10-17`, middletown `2026-10-18`; on Sun 2026-10-11 → same dates; on Thu 2026-10-15 → closed and dates `2026-10-24`/`2026-10-25`.
- [ ] Run `php cinnamon-roll-order-form/tests/run.php` → fails (missing file).
- [ ] Implement `catalog.php` and `menu.php`.
- [ ] Run tests → all pass; `php -l` both files.
- [ ] Commit `feat: add preorder catalog and menu endpoint`.

### Task 2: Checkout endpoint

**Files:** Create `api/helpers/checkout_lib.php`, `api/helpers/square.php`, `api/checkout.php`; extend `tests/run.php`.

**Interfaces — consumes:** Task 1 functions. **Produces:**
- `validateCheckout(mixed $body, DateTimeImmutable $now): array{errors: string[], order: ?array}` where `order = ['token','key','contact'=>[first,last,email,phone],'pickup'=>[id,label,date,time,pickup_at],'lines'=>[[id,name,qty,unit_cents,line_cents]],'total_cents']`
- `makeReferenceId(DateTimeImmutable $now): string` — `BB-YYYYMMDD-XXXX`
- `buildOrderBody(array $order, string $locationId, string $ref): array`
- `buildPaymentBody(array $order, string $locationId, string $ref, string $squareOrderId): array`
- `squareRequest(string $path, array $body): array{status:int, data:array, error:?string}`
- `squareDeclineMessage(string $code): string`

- [ ] Extend tests: valid body → no errors and `total_cents` 2400 for 2×roll + 1×tres leches; wrong `amount_cents` → error; unknown item, qty 0, qty 11, duplicate id, bad slot, bad email, missing token, bad UUID → errors; `buildOrderBody` has PICKUP fulfillment with RFC 3339 `pickup_at` `2026-10-17T10:30:00-04:00`; quantities are strings.
- [ ] Run → fails.
- [ ] Implement the three files.
- [ ] Run → pass; `php -l` all.
- [ ] Commit `feat: add Square checkout endpoint for native preorder form`.

### Task 3: Demo page

**Files:** Create `order-demo/index.html`, `assets/js/order-native.js`; modify `styles.css` (append).

**Interfaces — consumes:** `GET {api}/menu.php`, `GET {api}/config.php`, `POST {api}/checkout.php` (shapes in spec).

- [ ] Build page (nav/footer copied from `/order/`, `noindex`, closed-state IDs for `preorder-window.js`).
- [ ] Build `order-native.js`: render menu + pickup, steppers, totals, Square SDK (card, Apple Pay, Google Pay, Cash App Pay), validation, submit, confirmation.
- [ ] Append styles.
- [ ] Serve with `php -S localhost:8000` from repo root; load `/order-demo/`; no console errors; totals update; wallet buttons render where supported.
- [ ] Commit `feat: add native Square preorder demo page`.

### Task 4: Sandbox verification

- [ ] Sandbox card `4111 1111 1111 1111` → confirmation screen with receipt link; order visible with pickup fulfillment in sandbox Seller Dashboard.
- [ ] Decline card `4000 0000 0000 0002` → decline message, no confirmation.
- [ ] `curl` POST with tampered `amount_cents` → 422.
- [ ] Note results in the final summary.
