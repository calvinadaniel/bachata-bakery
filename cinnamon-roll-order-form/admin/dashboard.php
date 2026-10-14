<?php
/**
 * admin/dashboard.php
 *
 * Owner dashboard: window selector, stats, pause switch, sell-out limits,
 * force-close toggle, order table.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/window.php';
require_once dirname(__DIR__) . '/api/helpers/db.php';
require_once dirname(__DIR__) . '/api/helpers/time_gate.php';
require_once dirname(__DIR__) . '/api/helpers/cap.php';
require_once dirname(__DIR__) . '/api/helpers/store.php';

$pdo = db();
$now = new DateTimeImmutable('now', _bakeryTz());

// ---------------------------------------------------------------
// Window being viewed (?window=YYYY-MM-DD, default: pickups now / orders now)
// ---------------------------------------------------------------
$windowId      = requestedWindowId($_GET['window'] ?? null);
$windowOptions = adminWindowOptions($pdo, $now);
if (!in_array($windowId, $windowOptions, true)) {
    $windowOptions[] = $windowId;
    rsort($windowOptions);
}
$store       = storeStatus($pdo, $now);
$pausedUntil = getSetting($pdo, 'paused_until');
$pauseMsg    = getSetting($pdo, 'pause_message');
$isPaused    = $store['closed_reason'] === 'vacation';

// ---------------------------------------------------------------
// Stats
// ---------------------------------------------------------------
$cap = getCapRow($pdo, $windowId);

$revStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount_cents),0) FROM orders WHERE window_id=? AND payment_status='paid'"
);
$revStmt->execute([$windowId]);
$revenueCents = (int) $revStmt->fetchColumn();

// ---------------------------------------------------------------
// Order list — with optional search
// ---------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$like   = '%' . $search . '%';

$orderStmt = $pdo->prepare(
    "SELECT order_ref, customer_name, customer_email, customer_phone,
            product_variant, quantity, amount_cents, pickup_date, pickup_location, pickup_time,
            payment_status, created_at
       FROM orders
      WHERE window_id = :win
        AND (:bare OR customer_name LIKE :q_name OR customer_email LIKE :q_email)
      ORDER BY created_at DESC"
);
$orderStmt->execute([':win' => $windowId, ':bare' => (int) ($search === ''), ':q_name' => $like, ':q_email' => $like]);
$orders = $orderStmt->fetchAll();

// ---------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------
$flashMap = [
    'force_close_updated' => ['Form status updated.', 'success'],
    'caps_updated'        => ['Sell-out limits updated.', 'success'],
    'paused'              => ['Orders paused. The order page now shows your message.', 'success'],
    'resumed'             => ['Pause cleared — the weekly schedule is back on.', 'success'],
    'pause_invalid'       => ['Pick a reopen date after today (within a year).', 'error'],
];
$flash    = $flashMap[$_GET['msg'] ?? ''] ?? null;

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function pct(int $used, int $max): int
{
    return $max > 0 ? min(100, (int) round($used / $max * 100)) : 0;
}
function money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}
function statusBadge(string $s): string
{
    $map = [
        'paid'    => ['#E6F6F4', '#157E73', 'Paid'],
        'pending' => ['#FEF5E7', '#A06B00', 'Pending'],
        'failed'  => ['#FDF0EF', '#B91A17', 'Failed'],
    ];
    [$bg, $color, $label] = $map[$s] ?? ['#F5EFE6', '#7A5C44', $s];
    return "<span style=\"background:{$bg};color:{$color};padding:3px 10px;border-radius:20px;"
         . "font-size:12px;font-weight:700;white-space:nowrap;\">{$label}</span>";
}
function esc(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
/** "Orders Oct 11–14 · pickup Oct 17–18" for a window id (its closing Wednesday). */
function windowLabel(string $windowId): string
{
    $wed = new DateTimeImmutable($windowId);
    return 'Orders ' . $wed->modify('-3 days')->format('M j') . '–' . $wed->format('j')
         . ' · pickup ' . $wed->modify('+3 days')->format('M j') . '–' . $wed->modify('+4 days')->format('j');
}
function pickupLabel(array $o): string
{
    $parts = array_filter([
        $o['pickup_date'] ? (new DateTimeImmutable($o['pickup_date']))->format('D M j') : null,
        $o['pickup_time'] ? date('g:i A', strtotime($o['pickup_time'])) : null,
    ]);
    return implode(' ', $parts) ?: '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Bachata Bakery</title>
  <style>
    /* ── Reset & base ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      font-size: 15px;
      line-height: 1.5;
      color: #3B1A08;
      background: #EDE4D6;
      min-height: 100vh;
    }
    a { color: #1A9E8F; }
    /* ── Layout ── */
    .topbar {
      background: #3B1A08;
      padding: 14px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .topbar-brand {
      font-family: Georgia, serif;
      font-size: 18px;
      color: #F5EFE6;
    }
    .topbar-brand span {
      font-size: 12px;
      color: #F4A228;
      font-style: italic;
      margin-left: 10px;
      font-family: inherit;
    }
    .topbar-nav { display: flex; gap: 16px; align-items: center; }
    .topbar-nav a {
      font-size: 13px;
      color: rgba(245,239,230,0.7);
      text-decoration: none;
      font-weight: 500;
    }
    .topbar-nav a:hover { color: #F5EFE6; }
    .main { max-width: 1100px; margin: 0 auto; padding: 28px 24px 60px; }
    /* ── Section headings ── */
    .section-title {
      font-family: Georgia, serif;
      font-size: 18px;
      font-weight: 700;
      color: #3B1A08;
      margin-bottom: 16px;
    }
    .section { margin-bottom: 36px; }
    /* ── Flash ── */
    .flash {
      padding: 12px 18px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 24px;
    }
    .flash-success { background: #E6F6F4; color: #157E73; border: 1.5px solid rgba(26,158,143,0.25); }
    .flash-error   { background: #FDF0EF; color: #B91A17; border: 1.5px solid rgba(229,37,33,0.25); }
    /* ── Window selector ── */
    .window-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 24px; }
    .window-bar .window-badge { margin-bottom: 0; }
    .window-select {
      padding: 7px 12px;
      font-family: inherit;
      font-size: 14px;
      color: #3B1A08;
      background: #fff;
      border: 1.5px solid #D9CEBF;
      border-radius: 8px;
    }
    /* Pause card */
    .pause-card { flex: 1; min-width: 280px; }
    .pause-card textarea.num-input { font-size: 14px; font-weight: 400; resize: vertical; min-height: 64px; }
    .pause-active { font-size: 14px; margin-bottom: 12px; }
    .btn-ghost {
      padding: 9px 18px;
      background: #F5EFE6;
      color: #3B1A08;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      border: 1.5px solid #D9CEBF;
      border-radius: 8px;
      cursor: pointer;
    }
    /* ── Window badge ── */
    .window-badge {
      display: inline-block;
      background: #F5EFE6;
      border: 1.5px solid #D9CEBF;
      border-radius: 20px;
      padding: 4px 14px;
      font-size: 13px;
      color: #7A5C44;
      font-weight: 600;
      margin-bottom: 24px;
    }
    /* ── Stats grid ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: #fff;
      border-radius: 12px;
      padding: 20px 22px;
      box-shadow: 0 2px 8px rgba(59,26,8,0.07);
    }
    .stat-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #7A5C44;
      margin-bottom: 8px;
    }
    .stat-value {
      font-family: Georgia, serif;
      font-size: 30px;
      font-weight: 700;
      color: #3B1A08;
      line-height: 1;
      margin-bottom: 10px;
    }
    .stat-value small {
      font-size: 16px;
      color: #7A5C44;
      font-family: inherit;
    }
    .bar-track {
      height: 6px;
      background: #EDE4D6;
      border-radius: 99px;
      overflow: hidden;
    }
    .bar-fill {
      height: 100%;
      border-radius: 99px;
      background: #1A9E8F;
      transition: width 0.4s ease;
    }
    .bar-fill--warn  { background: #F4A228; }
    .bar-fill--full  { background: #E52521; }
    /* ── Controls row ── */
    .controls-row {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      align-items: flex-start;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      padding: 22px 24px;
      box-shadow: 0 2px 8px rgba(59,26,8,0.07);
    }
    .card-title {
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #7A5C44;
      margin-bottom: 14px;
    }
    /* Force-close toggle */
    .force-close-card { flex: 0 0 auto; }
    .btn-force {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.15s;
    }
    .btn-force:hover { opacity: 0.88; }
    .btn-force--open  { background: #E52521; color: #fff; }
    .btn-force--closed { background: #1A9E8F; color: #fff; }
    .force-status {
      font-size: 13px;
      color: #7A5C44;
      margin-top: 10px;
    }
    /* Cap override form */
    .cap-override-card { flex: 1; min-width: 280px; }
    .cap-fields { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
    .field-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 110px; }
    .field-group label {
      font-size: 12px;
      font-weight: 600;
      color: #7A5C44;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .num-input {
      padding: 9px 12px;
      font-family: inherit;
      font-size: 16px;
      font-weight: 600;
      color: #3B1A08;
      background: #fff;
      border: 1.5px solid #D9CEBF;
      border-radius: 8px;
      width: 100%;
      outline: none;
    }
    .num-input:focus { border-color: #1A9E8F; box-shadow: 0 0 0 3px rgba(26,158,143,0.15); }
    .btn-save {
      padding: 9px 22px;
      background: #3B1A08;
      color: #F5EFE6;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      white-space: nowrap;
      transition: background 0.15s;
      align-self: flex-end;
    }
    .btn-save:hover { background: #5C2E0E; }
    /* ── Orders table ── */
    .orders-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 14px;
    }
    .search-form { display: flex; gap: 8px; }
    .search-input {
      padding: 8px 14px;
      font-family: inherit;
      font-size: 14px;
      border: 1.5px solid #D9CEBF;
      border-radius: 8px;
      outline: none;
      width: 220px;
      background: #fff;
    }
    .search-input:focus { border-color: #1A9E8F; }
    .btn-search {
      padding: 8px 16px;
      background: #1A9E8F;
      color: #fff;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }
    .btn-export {
      padding: 8px 16px;
      background: #F5EFE6;
      color: #3B1A08;
      font-family: inherit;
      font-size: 13px;
      font-weight: 600;
      border: 1.5px solid #D9CEBF;
      border-radius: 8px;
      text-decoration: none;
      white-space: nowrap;
      transition: background 0.15s;
    }
    .btn-export:hover { background: #EDE4D6; }
    .table-wrap { overflow-x: auto; border-radius: 12px; box-shadow: 0 2px 8px rgba(59,26,8,0.07); }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      font-size: 14px;
    }
    thead th {
      background: #3B1A08;
      color: #F5EFE6;
      padding: 12px 14px;
      text-align: left;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      white-space: nowrap;
      cursor: pointer;
      user-select: none;
    }
    thead th:hover { background: #5C2E0E; }
    thead th.sorted-asc::after  { content: ' ↑'; }
    thead th.sorted-desc::after { content: ' ↓'; }
    tbody tr:nth-child(even) { background: #FDFAF6; }
    tbody tr:hover { background: #F5EFE6; }
    tbody td {
      padding: 11px 14px;
      border-bottom: 1px solid #EDE4D6;
      white-space: nowrap;
    }
    tbody td.wrap { white-space: normal; max-width: 180px; }
    .no-orders { text-align: center; padding: 40px; color: #7A5C44; font-style: italic; }
    /* responsive */
    @media (max-width: 600px) {
      .main { padding: 16px 12px 40px; }
      .controls-row { flex-direction: column; }
      .cap-override-card { width: 100%; }
    }
  </style>
</head>
<body>

  <!-- Top bar -->
  <div class="topbar">
    <div class="topbar-brand">
      Bachata Bakery <span>Owner Dashboard</span>
    </div>
    <nav class="topbar-nav">
      <a href="<?= esc(adminUrl('actions/export-csv.php') . '?window=' . $windowId) ?>">↓ Export CSV</a>
      <a href="<?= esc(adminUrl('logout.php')) ?>">Sign out</a>
    </nav>
  </div>

  <div class="main">

    <?php if ($flash): ?>
      <p class="flash flash-<?= esc($flash[1]) ?>">
        <?= esc($flash[0]) ?>
      </p>
    <?php endif; ?>

    <div class="window-bar">
      <form method="GET" action="">
        <label for="window-select" class="card-title" style="margin:0 8px 0 0;">Week</label>
        <select id="window-select" class="window-select" name="window" onchange="this.form.submit()">
          <?php foreach ($windowOptions as $opt): ?>
            <option value="<?= esc($opt) ?>" <?= $opt === $windowId ? 'selected' : '' ?>><?= esc(windowLabel($opt)) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn-search" type="submit">Show</button></noscript>
      </form>
      <p class="window-badge">
        Order page right now:
        <?php if ($store['open']): ?>
          <strong style="color:#1A9E8F;">Open</strong> · <?= (int) $store['items_remaining'] ?> items left
        <?php else: ?>
          <strong style="color:#E52521;">Closed</strong>
          (<?= esc(['vacation' => 'paused', 'time_gate' => 'outside Sun–Wed', 'force_closed' => 'force closed', 'sold_out' => 'sold out'][$store['closed_reason']] ?? $store['closed_reason']) ?>)
        <?php endif; ?>
      </p>
    </div>

    <!-- ── Stats ── -->
    <section class="section">
      <h2 class="section-title"><?= esc(windowLabel($windowId)) ?></h2>
      <div class="stats-grid">

        <?php
        $rollsPct  = pct((int)$cap['rolls_sold'],    (int)$cap['rolls_max']);
        $ordersPct = pct((int)$cap['orders_placed'], (int)$cap['orders_max']);
        $barRollsClass  = $rollsPct  >= 100 ? 'bar-fill--full' : ($rollsPct  >= 75 ? 'bar-fill--warn' : '');
        $barOrdersClass = $ordersPct >= 100 ? 'bar-fill--full' : ($ordersPct >= 75 ? 'bar-fill--warn' : '');
        ?>

        <div class="stat-card">
          <p class="stat-label">Items sold</p>
          <p class="stat-value">
            <?= (int)$cap['rolls_sold'] ?>
            <small>/ <?= (int)$cap['rolls_max'] ?></small>
          </p>
          <div class="bar-track">
            <div class="bar-fill <?= $barRollsClass ?>"
                 style="width:<?= $rollsPct ?>%"></div>
          </div>
        </div>

        <div class="stat-card">
          <p class="stat-label">Orders placed</p>
          <p class="stat-value">
            <?= (int)$cap['orders_placed'] ?>
            <small>/ <?= (int)$cap['orders_max'] ?></small>
          </p>
          <div class="bar-track">
            <div class="bar-fill <?= $barOrdersClass ?>"
                 style="width:<?= $ordersPct ?>%"></div>
          </div>
        </div>

        <div class="stat-card">
          <p class="stat-label">Revenue (paid)</p>
          <p class="stat-value"><?= money($revenueCents) ?></p>
          <div class="bar-track" style="height:6px;">
            <div class="bar-fill" style="width:100%;background:#F4A228;"></div>
          </div>
        </div>

      </div>
    </section>

    <!-- ── Controls ── -->
    <section class="section">
      <h2 class="section-title">Controls</h2>
      <div class="controls-row">

        <!-- Vacation / pause (all weeks) -->
        <div class="card pause-card">
          <p class="card-title">Pause orders (vacation)</p>
          <?php if ($isPaused): ?>
            <p class="pause-active">
              <strong style="color:#E52521;">Paused</strong> until
              <?= esc((new DateTimeImmutable($pausedUntil))->format('l, M j')) ?>.
              <?= $pauseMsg ? '<br>Message: “' . esc($pauseMsg) . '”' : '' ?>
            </p>
            <form method="POST" action="<?= esc(adminUrl('actions/pause.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
              <input type="hidden" name="window_id" value="<?= esc($windowId) ?>">
              <input type="hidden" name="action" value="resume">
              <button type="submit" class="btn-force btn-force--closed">&#9654; Resume now</button>
            </form>
          <?php else: ?>
            <form method="POST" action="<?= esc(adminUrl('actions/pause.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
              <input type="hidden" name="window_id" value="<?= esc($windowId) ?>">
              <input type="hidden" name="action" value="pause">
              <div class="cap-fields">
                <div class="field-group">
                  <label for="paused-until">Closed until</label>
                  <input class="num-input" type="date" id="paused-until" name="paused_until" required
                         min="<?= esc($now->modify('+1 day')->format('Y-m-d')) ?>"
                         max="<?= esc($now->modify('+1 year')->format('Y-m-d')) ?>">
                </div>
              </div>
              <div class="field-group" style="margin-top:12px;">
                <label for="pause-message">Message for customers (optional)</label>
                <textarea class="num-input" id="pause-message" name="pause_message" maxlength="280"
                          placeholder="We’re on vacation! Orders reopen Sunday, Nov 1."></textarea>
              </div>
              <button type="submit" class="btn-save" style="margin-top:12px;">Pause orders</button>
            </form>
          <?php endif; ?>
        </div>

        <!-- Force-close toggle -->
        <div class="card force-close-card">
          <p class="card-title">This week’s form</p>
          <form method="POST" action="<?= esc(adminUrl('actions/force-close.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
            <input type="hidden" name="window_id" value="<?= esc($windowId) ?>">
            <?php if ((int)$cap['force_closed'] === 1): ?>
              <button type="submit" class="btn-force btn-force--closed">
                &#9654; Re-open form
              </button>
              <p class="force-status">Status: <strong style="color:#E52521;">Force closed</strong></p>
            <?php else: ?>
              <button type="submit" class="btn-force btn-force--open">
                &#9632; Force close form
              </button>
              <p class="force-status">Status: <strong style="color:#1A9E8F;">Open / auto</strong></p>
            <?php endif; ?>
          </form>
        </div>

        <!-- Cap override -->
        <div class="card cap-override-card">
          <p class="card-title">Weekly sell-out limits</p>
          <form method="POST" action="<?= esc(adminUrl('actions/cap-override.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
            <input type="hidden" name="window_id" value="<?= esc($windowId) ?>">
            <div class="cap-fields">
              <div class="field-group">
                <label for="rolls-max">Max items</label>
                <input class="num-input" type="number" id="rolls-max" name="rolls_max"
                       min="1" max="500"
                       value="<?= (int)$cap['rolls_max'] ?>">
              </div>
              <div class="field-group">
                <label for="orders-max">Max orders</label>
                <input class="num-input" type="number" id="orders-max" name="orders_max"
                       min="1" max="200"
                       value="<?= (int)$cap['orders_max'] ?>">
              </div>
              <button type="submit" class="btn-save">Save limits</button>
            </div>
          </form>
        </div>

      </div>
    </section>

    <!-- ── Orders table ── -->
    <section class="section">
      <div class="orders-header">
        <h2 class="section-title" style="margin:0;">
          Orders
          <small style="font-size:14px;font-weight:400;color:#7A5C44;font-family:inherit;">
            (<?= count($orders) ?> <?= $search !== '' ? 'results' : 'total' ?>)
          </small>
        </h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <form class="search-form" method="GET" action="">
            <input type="hidden" name="window" value="<?= esc($windowId) ?>">
            <input class="search-input" type="search" name="q"
                   placeholder="Search name or email&hellip;"
                   value="<?= esc($search) ?>">
            <button class="btn-search" type="submit">Search</button>
            <?php if ($search !== ''): ?>
              <a href="<?= esc(adminUrl('dashboard.php') . '?window=' . $windowId) ?>"
                 style="font-size:13px;color:#7A5C44;text-decoration:none;">
                Clear
              </a>
            <?php endif; ?>
          </form>
          <a class="btn-export" href="<?= esc(adminUrl('actions/export-csv.php') . '?window=' . $windowId) ?>">
            ↓ Export CSV
          </a>
        </div>
      </div>

      <div class="table-wrap">
        <table id="orders-table">
          <thead>
            <tr>
              <th data-col="0">Ref</th>
              <th data-col="1">Name</th>
              <th data-col="2">Email</th>
              <th data-col="3">Phone</th>
              <th data-col="4">Items</th>
              <th data-col="5">Qty</th>
              <th data-col="6">Total</th>
              <th data-col="7">Pickup</th>
              <th data-col="8">Status</th>
              <th data-col="9">Ordered at</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="10" class="no-orders">
                  <?= $search !== '' ? 'No orders match your search.' : 'No orders for this window yet.' ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $o): ?>
                <tr>
                  <td><code style="font-size:12px;"><?= esc($o['order_ref']) ?></code></td>
                  <td><?= esc($o['customer_name']) ?></td>
                  <td>
                    <a href="mailto:<?= esc($o['customer_email']) ?>"
                       style="color:#1A9E8F;text-decoration:none;">
                      <?= esc($o['customer_email']) ?>
                    </a>
                  </td>
                  <td><?= esc($o['customer_phone'] ?? '—') ?></td>
                  <td class="wrap"><?= esc($o['product_variant'] ?? '—') ?></td>
                  <td style="text-align:center;font-weight:700;"><?= (int)$o['quantity'] ?></td>
                  <td style="font-weight:600;"><?= money((int)$o['amount_cents']) ?></td>
                  <td class="wrap">
                    <?= esc(pickupLabel($o)) ?>
                    <?php if ($o['pickup_location']): ?>
                      <br><small style="color:#7A5C44;"><?= esc($o['pickup_location']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><?= statusBadge($o['payment_status']) ?></td>
                  <td style="color:#7A5C44;font-size:13px;"><?= esc($o['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div><!-- /main -->

  <script>
    // ── Vanilla JS client-side table sort ──
    (function () {
      'use strict';

      const table   = document.getElementById('orders-table');
      const headers = table.querySelectorAll('thead th');
      let lastCol   = -1;
      let ascending = true;

      headers.forEach(th => {
        th.addEventListener('click', () => {
          const col = parseInt(th.dataset.col, 10);
          ascending = (col === lastCol) ? !ascending : true;
          lastCol   = col;

          headers.forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
          th.classList.add(ascending ? 'sorted-asc' : 'sorted-desc');

          const tbody = table.querySelector('tbody');
          const rows  = Array.from(tbody.querySelectorAll('tr'));

          // Skip the "no orders" row
          if (rows.length === 1 && rows[0].querySelector('td[colspan]')) return;

          rows.sort((a, b) => {
            const aText = (a.cells[col]?.textContent ?? '').trim();
            const bText = (b.cells[col]?.textContent ?? '').trim();
            const aNum  = parseFloat(aText.replace(/[^0-9.-]/g, ''));
            const bNum  = parseFloat(bText.replace(/[^0-9.-]/g, ''));
            const cmp   = !isNaN(aNum) && !isNaN(bNum)
              ? aNum - bNum
              : aText.localeCompare(bText);
            return ascending ? cmp : -cmp;
          });

          rows.forEach(r => tbody.appendChild(r));
        });
      });
    }());
  </script>

</body>
</html>
