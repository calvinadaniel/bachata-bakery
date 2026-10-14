# Native preorder form — go-live checklist

Takes the native Square order form (`/order-demo/` + `cinnamon-roll-order-form/`)
from local sandbox to taking real payments on bachatabakery.com.

Do it in two passes: **dev first** (dev.bachatabakery.com, Square sandbox), then
**production**. Never commit a `.env`; keep them outside the repo
(e.g. `C:\secure\bachata-dev.env`, `C:\secure\bachata-prod.env`).

---

## 1. Accounts and keys (one-time)

- [ ] **hCaptcha** — sign up at <https://dashboard.hcaptcha.com>, add a site for
      `bachatabakery.com` and `dev.bachatabakery.com`. Copy the **site key** and
      the account **secret**. (Production refuses to take orders with the
      `0x000…` test secret.)
- [ ] **Square production credentials** — Square Developer Dashboard → your app →
      switch to **Production** → copy the Application ID, Access token, and the
      bakery's Location ID.
- [ ] **Admin password** — pick a 16+ character password for `ADMIN_PASS`
      (a password manager is ideal). The dashboard refuses shorter ones in production.

## 2. Databases on Hostinger

One database for dev, one for production (hPanel → Databases → MySQL Databases,
or the Hostinger CLI `hosting databases create`). Names and users come out as
`u983304174_…`.

- [ ] Create the **dev** database + user; note name, user, password.
- [ ] Create the **production** database + user.
- [ ] For each: phpMyAdmin → select the database → Import → `cinnamon-roll-order-form/schema.sql`.
      Expect four tables: `orders`, `order_caps`, `store_settings`, `checkout_attempts`.

## 3. Dev pass (Square sandbox)

- [ ] Create `C:\secure\bachata-dev.env` from `cinnamon-roll-order-form/.env.example`:
  - `APP_ENV=sandbox`, the **sandbox** Square app id / location / token
  - `DB_HOST=localhost`, the dev `DB_NAME` / `DB_USER` / `DB_PASS`
  - hCaptcha site key + secret, `ADMIN_PASS`, `BAKERY_TIMEZONE=America/New_York`
  - `WEBHOOK_URL=https://dev.bachatabakery.com/cinnamon-roll-order-form/api/webhook/square.php`
- [ ] Square Developer Dashboard (**Sandbox**) → Webhooks → add subscription:
      URL above, events `payment.created` and `payment.updated`. Copy its
      **signature key** into `SQUARE_WEBHOOK_SIG_KEY`.
- [ ] Deploy:
  ```powershell
  ./scripts/deploy-backend.ps1 -Target dev -EnvFile C:\secure\bachata-dev.env
  ./scripts/deploy.ps1 -Target dev
  ./scripts/deploy.ps1 -Target dev -Files order-demo/index.html, assets/js/order-native.js
  ```
  The backend script's smoke test must be all `ok` (API answers; `.env`,
  helpers, tests, schema are refused).
- [ ] On <https://dev.bachatabakery.com/order-demo/>: place a sandbox order with
      card `4111 1111 1111 1111` and a decline with `4000 0000 0000 0002`.
- [ ] <https://dev.bachatabakery.com/cinnamon-roll-order-form/admin/> — the paid
      order appears with items and pickup time; the declined one shows **Failed**.
- [ ] Square Sandbox Dashboard → Webhooks → the deliveries show **200**.
- [ ] **Client IP check (CDN):** place one attempt from home Wi-Fi and one from
      a phone on cellular. In phpMyAdmin, `SELECT ip_hash, created_at FROM
      checkout_attempts ORDER BY id DESC LIMIT 4;` — the two networks must show
      **different** `ip_hash` values. If they're identical, Hostinger's CDN is
      hiding the visitor IP and rate limiting would lock out everyone at once —
      stop and fix `clientIpHash()` before production.
- [ ] Pause and resume from the dashboard; the order page shows/clears the message.

## 4. Production pass

- [ ] Create `C:\secure\bachata-prod.env` like the dev one but with:
  - `APP_ENV=production`, the **production** Square app id / location / token
  - the production database credentials
  - `WEBHOOK_URL=https://bachatabakery.com/cinnamon-roll-order-form/api/webhook/square.php`
- [ ] Square Developer Dashboard (**Production**) → Webhooks → same two events,
      production URL; copy the signature key into `SQUARE_WEBHOOK_SIG_KEY`.
- [ ] Deploy the backend (the script refuses the file if anything above is off):
  ```powershell
  ./scripts/deploy-backend.ps1 -EnvFile C:\secure\bachata-prod.env
  ```
- [ ] **Apple Pay domain** — Square Dashboard → Settings → Apple Pay (Web) → add
      `bachatabakery.com`, download the verification file, save it as
      `.well-known/apple-developer-merchantid-domain-association`, then:
  ```powershell
  ./scripts/deploy.ps1 -Files .well-known/apple-developer-merchantid-domain-association
  ```
  and click **Verify** in Square. (Google Pay and Cash App Pay need no domain file.)
- [ ] Deploy the order page:
  ```powershell
  ./scripts/deploy.ps1 -Files order-demo/index.html, assets/js/order-native.js, styles.css, assets/js/preorder-window.js
  ```
- [ ] Admin dashboard → **Weekly sell-out limits**: set this week's max items / max orders.

## 5. Live test (real money)

- [ ] On <https://bachatabakery.com/order-demo/> (no sandbox banner should show)
      buy the cheapest item (1 sugar cookie, $7) with a real card.
- [ ] Confirm: confirmation screen + Square receipt email; the order in the Square
      Dashboard (Orders, with the pickup details); the order **Paid** in the admin
      dashboard; Square webhook deliveries **200**.
- [ ] Refund it in the Square Dashboard.
- [ ] Try Apple Pay on an iPhone (Safari) and Google Pay on Android/Chrome.

## 6. Cut over from Jotform

- [ ] The native form stays a **separate page** (`/order-demo/`); `/order/` keeps
      the Jotform form. When ready, link to the native page from the site (and
      drop its `noindex`) rather than editing `order/index.html`.
      Until then `/order-demo/` stays unlisted (`noindex`).
- [ ] After the first real week, export the CSV from the dashboard and compare
      with Square's sales report.

## If something goes wrong

- **Stop orders immediately:** admin dashboard → *Force close form* (this week)
  or *Pause orders* (until a date). Checkout refuses new payments right away.
- **Payment taken but order shows Pending:** the Square webhook settles it
  automatically; if it doesn't within a few minutes, check the webhook
  deliveries in the Square Dashboard.
- **Roll back the page:** redeploy the previous `order/index.html` (Jotform).
