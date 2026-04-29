'use strict';

/* =============================================================
   Bachata Bakery — form.js
   Handles: status polling, countdown, UI state, form validation,
   quantity picker, and order submission (Square token stub for Phase 4).
   ============================================================= */

// -------------------------------------------------------------
// Constants
// -------------------------------------------------------------
const ROLL_PRICE_CENTS  = 600;          // $6.00 per roll — update to match actual price
const MAX_QTY_PER_ORDER = 12;
const ROLLS_CAP_DEFAULT = 100;          // mirrors order_caps.rolls_max default
const ORDERS_CAP_DEFAULT = 50;          // mirrors order_caps.orders_max default
const POLL_INTERVAL_MS  = 60_000;

const STATUS_ENDPOINT = 'api/status.php';
const ORDER_ENDPOINT  = 'api/order.php';

// -------------------------------------------------------------
// Application state
// -------------------------------------------------------------
let state = {
  open:            false,
  rollsRemaining:  0,
  ordersRemaining: 0,
  forceClosed:     false,
  closedReason:    'time_gate',   // 'time_gate' | 'sold_out' | 'force_closed'
  nextOpen:        null,          // Date — next Friday midnight (bakery tz)
  windowFriday:    null,          // Date — this window's Friday (derived)
  qty:             1,
};

let pollTimer      = null;
let countdownTimer = null;
let submitting     = false;

// Square Web Payments SDK state
let squarePayments = null;
let squareCard     = null;

// -------------------------------------------------------------
// DOM references — grabbed once on DOMContentLoaded
// -------------------------------------------------------------
const dom = {};

function initDom() {
  dom.capacityBanner = document.getElementById('capacity-banner');
  dom.closedSection  = document.getElementById('closed-section');
  dom.orderSection   = document.getElementById('order-section');
  dom.formMessage    = document.getElementById('form-message');

  // Closed section
  dom.closedHeading = document.getElementById('closed-heading');
  dom.closedSub     = document.getElementById('closed-sub');
  dom.nextOpenNote  = document.getElementById('next-open-note');
  dom.cdDays        = document.getElementById('cd-days');
  dom.cdHours       = document.getElementById('cd-hours');
  dom.cdMins        = document.getElementById('cd-mins');
  dom.cdSecs        = document.getElementById('cd-secs');

  // Capacity banner
  dom.rollsCount  = document.getElementById('rolls-count');
  dom.ordersCount = document.getElementById('orders-count');
  dom.rollsBar    = document.getElementById('rolls-bar');
  dom.ordersBar   = document.getElementById('orders-bar');

  // Form fields
  dom.form        = document.getElementById('order-form');
  dom.nameInput   = document.getElementById('customer-name');
  dom.emailInput  = document.getElementById('customer-email');
  dom.phoneInput  = document.getElementById('customer-phone');
  dom.variantSel  = document.getElementById('variant');
  dom.qtyDisplay  = document.getElementById('qty-display');
  dom.qtyInput    = document.getElementById('quantity');
  dom.qtyNote     = document.getElementById('qty-note');
  dom.qtyDec      = dom.form.querySelector('.qty-dec');
  dom.qtyInc      = dom.form.querySelector('.qty-inc');
  dom.pickupDate  = document.getElementById('pickup-date');
  dom.notes       = document.getElementById('special-notes');
  dom.totalDisplay = document.getElementById('order-total-display');
  dom.submitBtn   = document.getElementById('submit-btn');
  dom.btnLabel    = dom.submitBtn.querySelector('.btn-label');
  dom.btnSpinner  = dom.submitBtn.querySelector('.btn-spinner');
}

// -------------------------------------------------------------
// Status polling
// -------------------------------------------------------------
async function pollStatus() {
  try {
    const res = await fetch(STATUS_ENDPOINT, { cache: 'no-store' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    applyState(data);
    renderUI();
  } catch (err) {
    console.error('[BachataBakery] Status poll failed:', err.message);
    // Silently keep current UI — don't confuse user on transient network errors
  }
}

function applyState(data) {
  state.open            = !!data.open;
  state.rollsRemaining  = data.rolls_remaining  ?? 0;
  state.ordersRemaining = data.orders_remaining ?? 0;
  state.forceClosed     = !!data.force_closed;
  state.closedReason    = data.closed_reason ?? 'time_gate';
  state.nextOpen        = data.next_open ? new Date(data.next_open) : null;

  // Derive this window's Friday from nextOpen (which is always next Friday when open)
  if (state.open && state.nextOpen) {
    const friday = new Date(state.nextOpen);
    friday.setDate(friday.getDate() - 7);
    state.windowFriday = friday;
  } else {
    state.windowFriday = null;
  }
}

// -------------------------------------------------------------
// UI rendering
// -------------------------------------------------------------
function renderUI() {
  if (state.open) {
    show(dom.capacityBanner);
    show(dom.orderSection);
    hide(dom.closedSection);
    hide(dom.formMessage);

    updateCapacityBars();
    clampQtyToRemaining();
    populatePickupDates();
    stopCountdown();
  } else {
    hide(dom.capacityBanner);
    hide(dom.orderSection);
    show(dom.closedSection);

    updateClosedMessage();
    startCountdown();
  }
}

function updateCapacityBars() {
  dom.rollsCount.textContent  = state.rollsRemaining;
  dom.ordersCount.textContent = state.ordersRemaining;

  const rollsPct  = pct(state.rollsRemaining, ROLLS_CAP_DEFAULT);
  const ordersPct = pct(state.ordersRemaining, ORDERS_CAP_DEFAULT);

  dom.rollsBar.style.width  = `${rollsPct}%`;
  dom.ordersBar.style.width = `${ordersPct}%`;

  dom.rollsBar.setAttribute('aria-valuenow', state.rollsRemaining);
  dom.ordersBar.setAttribute('aria-valuenow', state.ordersRemaining);

  dom.rollsBar.classList.toggle('cap-bar--low',  state.rollsRemaining  <= 10);
  dom.ordersBar.classList.toggle('cap-bar--low', state.ordersRemaining <= 5);
}

function updateClosedMessage() {
  const isSoldOut = state.closedReason === 'sold_out'
                 || state.closedReason === 'force_closed';

  if (isSoldOut) {
    dom.closedHeading.textContent = 'Sold Out This Weekend';
    dom.closedSub.textContent     = 'All rolls are spoken for. Join us again next Friday!';
  } else {
    dom.closedHeading.textContent = 'Orders Open Every Friday';
    dom.closedSub.textContent     = 'Fresh cinnamon rolls, made to order — pick up on Saturday or Sunday.';
  }

  if (state.nextOpen) {
    dom.nextOpenNote.textContent = `Opens ${formatOpenDate(state.nextOpen)}`;
  }
}

// -------------------------------------------------------------
// Pickup date selector
// -------------------------------------------------------------
function populatePickupDates() {
  if (!state.windowFriday) return;

  const friday = state.windowFriday;

  const saturday = new Date(friday);
  saturday.setDate(friday.getDate() + 1);

  const sunday = new Date(friday);
  sunday.setDate(friday.getDate() + 2);

  // Preserve existing selection across polls
  const currentVal = dom.pickupDate.value;

  dom.pickupDate.length = 1; // keep the "Choose pickup day" placeholder

  dom.pickupDate.add(new Option(
    `Saturday — ${formatPickupLabel(saturday)}`,
    toDateString(saturday)
  ));
  dom.pickupDate.add(new Option(
    `Sunday — ${formatPickupLabel(sunday)}`,
    toDateString(sunday)
  ));

  if (currentVal) {
    dom.pickupDate.value = currentVal; // restore selection
  }
}

// -------------------------------------------------------------
// Countdown
// -------------------------------------------------------------
function startCountdown() {
  stopCountdown();
  if (!state.nextOpen) return;
  tickCountdown(); // immediate first tick
  countdownTimer = setInterval(tickCountdown, 1000);
}

function stopCountdown() {
  if (countdownTimer !== null) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }
}

function tickCountdown() {
  if (!state.nextOpen) return;

  const diffMs = state.nextOpen - Date.now();

  if (diffMs <= 0) {
    stopCountdown();
    setCountdownDisplay(0, 0, 0, 0);
    pollStatus(); // window just opened — refresh everything
    return;
  }

  const totalSecs = Math.floor(diffMs / 1000);
  const days  = Math.floor(totalSecs / 86400);
  const hours = Math.floor((totalSecs % 86400) / 3600);
  const mins  = Math.floor((totalSecs % 3600) / 60);
  const secs  = totalSecs % 60;

  setCountdownDisplay(days, hours, mins, secs);
}

function setCountdownDisplay(days, hours, mins, secs) {
  dom.cdDays.textContent  = days;
  dom.cdHours.textContent = pad(hours);
  dom.cdMins.textContent  = pad(mins);
  dom.cdSecs.textContent  = pad(secs);
}

// -------------------------------------------------------------
// Quantity picker
// -------------------------------------------------------------
function setQty(val) {
  const maxAllowed = Math.min(MAX_QTY_PER_ORDER, state.rollsRemaining || MAX_QTY_PER_ORDER);
  state.qty = clamp(val, 1, maxAllowed);

  dom.qtyDisplay.textContent = state.qty;
  dom.qtyInput.value         = state.qty;
  dom.qtyNote.textContent    = `Max ${maxAllowed} per order`;

  dom.qtyDec.disabled = state.qty <= 1;
  dom.qtyInc.disabled = state.qty >= maxAllowed;

  updateTotal();
}

function clampQtyToRemaining() {
  // Re-clamp if remaining dropped below current qty after a poll
  if (state.rollsRemaining > 0 && state.qty > state.rollsRemaining) {
    setQty(state.rollsRemaining);
  }
}

function updateTotal() {
  const cents = state.qty * ROLL_PRICE_CENTS;
  dom.totalDisplay.textContent = formatDollars(cents);
}

// -------------------------------------------------------------
// Form validation
// -------------------------------------------------------------
function validateForm() {
  let valid = true;

  // Required text/select fields
  const required = [
    { el: dom.nameInput,  errId: 'name-error',    msg: 'Please enter your full name.'       },
    { el: dom.emailInput, errId: 'email-error',   msg: 'Please enter your email address.'   },
    { el: dom.variantSel, errId: 'variant-error', msg: 'Please choose a variety.'           },
    { el: dom.pickupDate, errId: 'pickup-error',  msg: 'Please choose a pickup day.'        },
  ];

  required.forEach(({ el, errId, msg }) => {
    if (!el.value.trim()) {
      setFieldError(el, errId, msg);
      valid = false;
    } else {
      clearFieldError(el, errId);
    }
  });

  // Email format (only if value present)
  if (dom.emailInput.value.trim() && !isValidEmail(dom.emailInput.value)) {
    setFieldError(dom.emailInput, 'email-error', 'Please enter a valid email address.');
    valid = false;
  }

  // Quantity
  const maxAllowed = Math.min(MAX_QTY_PER_ORDER, state.rollsRemaining || MAX_QTY_PER_ORDER);
  if (state.qty < 1 || state.qty > maxAllowed) {
    document.getElementById('qty-error').textContent =
      `Quantity must be between 1 and ${maxAllowed}.`;
    valid = false;
  } else {
    document.getElementById('qty-error').textContent = '';
  }

  return valid;
}

function setFieldError(el, errId, msg) {
  document.getElementById(errId).textContent = msg;
  el.setAttribute('aria-invalid', 'true');
}

function clearFieldError(el, errId) {
  document.getElementById(errId).textContent = '';
  el.removeAttribute('aria-invalid');
}

function clearAllErrors() {
  document.querySelectorAll('.field-error').forEach(el => { el.textContent = ''; });
  document.querySelectorAll('[aria-invalid]').forEach(el => { el.removeAttribute('aria-invalid'); });
}

function focusFirstError() {
  const firstErrEl = dom.form.querySelector('.field-error:not(:empty)');
  if (!firstErrEl) return;
  const field = firstErrEl.closest('.field');
  const focusable = field?.querySelector('input, select, textarea');
  focusable?.focus();
}

// -------------------------------------------------------------
// Form submission
// Phase 4 replaces the token stub with Square tokenization.
// -------------------------------------------------------------
async function handleSubmit(e) {
  e.preventDefault();
  if (submitting) return;

  clearAllErrors();

  if (!validateForm()) {
    focusFirstError();
    return;
  }

  const token = await getCardToken();
  if (!token) return; // getCardToken() surfaces its own error to the user

  setSubmitting(true);

  try {
    const payload = {
      nonce:        token,
      name:         dom.nameInput.value.trim(),
      email:        dom.emailInput.value.trim(),
      phone:        dom.phoneInput.value.trim(),
      quantity:     state.qty,
      variant:      dom.variantSel.value,
      pickup_date:  dom.pickupDate.value,
      notes:        dom.notes.value.trim(),
      amount_cents: state.qty * ROLL_PRICE_CENTS,
    };

    const res  = await fetch(ORDER_ENDPOINT, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    });

    const data = await res.json();

    if (data.success) {
      showSuccess(data.order_ref);
    } else {
      handleOrderError(data.error_code, data.message);
    }
  } catch (err) {
    console.error('[BachataBakery] Order submission error:', err.message);
    showMessage('Something went wrong. Please try again in a moment.', 'error');
  } finally {
    setSubmitting(false);
  }
}

function handleOrderError(code, message) {
  const errorMessages = {
    form_closed:    'Orders are no longer open. Please check back this Friday.',
    sold_out_rolls: 'Not enough rolls remaining for your order size. Try a smaller quantity.',
    sold_out_orders:'All order slots are filled for this weekend. See you next Friday!',
    card_declined:  'Your card was declined. Please try a different card.',
    invalid_input:  'Please check your information and try again.',
    server_error:   'Something went wrong on our end. Please try again in a moment.',
  };

  const msg = errorMessages[code] ?? message ?? 'An unexpected error occurred.';
  showMessage(msg, 'error');

  // Refresh cap/status immediately if the form just closed
  if (['form_closed', 'sold_out_rolls', 'sold_out_orders'].includes(code)) {
    pollStatus();
  }
}

function showSuccess(orderRef) {
  hide(dom.orderSection);
  hide(dom.capacityBanner);

  dom.formMessage.innerHTML = `
    <div class="message-success">
      <h2>Order Confirmed!</h2>
      <p class="order-ref-display">${escHtml(orderRef)}</p>
      <p>A confirmation has been sent to your email. We'll see you at pickup!</p>
    </div>
  `;

  show(dom.formMessage);
  dom.formMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function showMessage(text, type = 'error') {
  dom.formMessage.innerHTML =
    `<p class="message-${escHtml(type)}">${escHtml(text)}</p>`;
  show(dom.formMessage);
}

function setSubmitting(active) {
  submitting             = active;
  dom.submitBtn.disabled = active;
  dom.btnLabel.hidden    = active;
  dom.btnSpinner.hidden  = !active;
}

// -------------------------------------------------------------
// Event listeners
// -------------------------------------------------------------
function attachListeners() {
  dom.qtyDec.addEventListener('click', () => setQty(state.qty - 1));
  dom.qtyInc.addEventListener('click', () => setQty(state.qty + 1));
  dom.form.addEventListener('submit', handleSubmit);

  // Clear individual field errors on input.
  // Explicit map avoids brittle string manipulation.
  const fieldErrorMap = {
    'customer-name':  'name-error',
    'customer-email': 'email-error',
    'customer-phone': 'phone-error',
    'variant':        'variant-error',
    'pickup-date':    'pickup-error',
  };

  dom.form.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('input', () => {
      const errId = fieldErrorMap[el.id];
      if (errId) {
        const errEl = document.getElementById(errId);
        if (errEl) errEl.textContent = '';
      }
      el.removeAttribute('aria-invalid');
    });
  });
}

// -------------------------------------------------------------
// Utilities
// -------------------------------------------------------------
function show(el) { el.hidden = false; }
function hide(el) { el.hidden = true; }

function pad(n) { return String(n).padStart(2, '0'); }

function clamp(val, min, max) { return Math.max(min, Math.min(max, val)); }

function pct(remaining, total) {
  return Math.max(0, Math.min(100, Math.round((remaining / total) * 100)));
}

function formatDollars(cents) {
  return `$${(cents / 100).toFixed(2)}`;
}

function formatOpenDate(date) {
  return date.toLocaleDateString('en-US', {
    weekday: 'long', month: 'long', day: 'numeric',
    hour: 'numeric', minute: '2-digit', timeZoneName: 'short',
  });
}

function formatPickupLabel(date) {
  return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
}

function toDateString(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function isValidEmail(val) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val);
}

function escHtml(str) {
  return String(str).replace(
    /[&<>"']/g,
    c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
  );
}

// -------------------------------------------------------------
// Square Web Payments SDK
// -------------------------------------------------------------

/**
 * Fetch public Square credentials from the server.
 * Only APP_ID, LOCATION_ID, and APP_ENV are returned — never the access token.
 */
async function fetchConfig() {
  const res = await fetch('api/config.php', { cache: 'no-store' });
  if (!res.ok) throw new Error(`Config fetch failed: HTTP ${res.status}`);
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data;
}

/**
 * Dynamically load the Square Web Payments SDK from the correct CDN
 * (sandbox vs. production) based on APP_ENV from the server.
 */
function loadSquareSdk(appEnv) {
  return new Promise((resolve, reject) => {
    if (window.Square) { resolve(); return; } // already loaded

    const url = appEnv === 'production'
      ? 'https://web.squarecdn.com/v1/square.js'
      : 'https://sandbox.web.squarecdn.com/v1/square.js';

    const script    = document.createElement('script');
    script.src      = url;
    script.onload   = resolve;
    script.onerror  = () => reject(new Error(`Failed to load Square SDK from ${url}`));
    document.head.appendChild(script);
  });
}

/**
 * Initialize the Square Payments instance and mount the card element
 * into #card-container. Called once after the SDK loads.
 */
async function initSquare(appId, locationId) {
  squarePayments = window.Square.payments(appId, locationId);

  squareCard = await squarePayments.card({
    style: {
      '.input-container': {
        borderColor:  '#D9CEBF',
        borderRadius: '8px',
      },
      '.input-container.is-focus': {
        borderColor: '#1A9E8F',
      },
      '.input-container.is-error': {
        borderColor: '#E52521',
      },
      '.message-text': {
        color: '#7A5C44',
      },
      '.message-icon': {
        color: '#E52521',
      },
    },
  });

  await squareCard.attach('#card-container');

  // Remove the placeholder text once Square has mounted its iframe
  const placeholder = document.querySelector('.card-placeholder');
  if (placeholder) placeholder.remove();
}

/**
 * Orchestrates config fetch → SDK load → card mount.
 * Called once during init(). Errors are non-fatal: the rest of the
 * form (status polling, countdown) continues to work.
 */
async function setupSquare() {
  const container = document.getElementById('card-container');

  try {
    container.innerHTML = '<p class="card-placeholder">Loading secure payment form\u2026</p>';

    const config = await fetchConfig();
    await loadSquareSdk(config.app_env);
    await initSquare(config.square_app_id, config.square_location_id);

  } catch (err) {
    console.error('[BachataBakery] Square setup failed:', err.message);
    container.innerHTML =
      '<p class="card-placeholder card-placeholder--error">' +
      'Payment form unavailable. Please refresh the page.' +
      '</p>';
  }
}

/**
 * Tokenize the card fields. Returns the nonce string on success,
 * or null on failure (errors are shown to the user inline).
 */
async function getCardToken() {
  if (!squareCard) {
    showMessage('Payment form is not ready. Please refresh the page.', 'error');
    return null;
  }

  // Clear any previous card-level error
  document.getElementById('card-error').textContent = '';

  const result = await squareCard.tokenize();

  if (result.status === 'OK') {
    return result.token;
  }

  // Square surfaces field errors inside the iframe automatically.
  // We additionally show a summary above the submit button.
  const summary = result.errors
    ?.map(e => e.message)
    .join(' ')
    ?? 'Please check your card details and try again.';

  document.getElementById('card-error').textContent = summary;
  return null;
}

// -------------------------------------------------------------
// Preview mode — ?preview=open bypasses backend for local UI testing
// -------------------------------------------------------------
function isPreviewMode() {
  return new URLSearchParams(window.location.search).get('preview') === 'open';
}

function applyPreviewState() {
  // Compute this window's Friday (most recent past Friday)
  const now    = new Date();
  const day    = now.getDay(); // 0=Sun … 6=Sat
  const friday = new Date(now);
  friday.setDate(now.getDate() - ((day + 2) % 7)); // roll back to Friday
  friday.setHours(0, 0, 0, 0);

  const nextFriday = new Date(friday);
  nextFriday.setDate(friday.getDate() + 7);

  applyState({
    open:             true,
    rolls_remaining:  75,
    orders_remaining: 30,
    force_closed:     false,
    closed_reason:    'time_gate',
    next_open:        nextFriday.toISOString(),
  });
}

// -------------------------------------------------------------
// Init
// -------------------------------------------------------------
async function init() {
  initDom();
  attachListeners();
  setQty(1);
  updateTotal();

  if (isPreviewMode()) {
    applyPreviewState();
    renderUI();
    document.getElementById('card-container').innerHTML =
      '<p class="card-placeholder">[Preview mode — payment form disabled]</p>';
    return;
  }

  // Kick off status poll and Square setup concurrently.
  // Status poll drives the UI immediately; Square setup runs alongside.
  await Promise.all([
    pollStatus(),
    setupSquare(),
  ]);

  pollTimer = setInterval(pollStatus, POLL_INTERVAL_MS);
}

document.addEventListener('DOMContentLoaded', init);
