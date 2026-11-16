/**
 * Bachata Bakery — native weekly preorder form (Square Web Payments SDK)
 *
 * Talks to cinnamon-roll-order-form/api:
 *   GET  menu.php      items, prices, pickup options, open state, items left this week
 *   GET  config.php    public Square app id / location id / env / hCaptcha site key
 *   POST checkout.php  token + captcha + order → Square order + payment
 *
 * Card, Apple Pay, Google Pay and Cash App Pay all produce a one-time token
 * that is sent to checkout.php; the server recomputes the total. The hCaptcha
 * check must be completed before any payment method is used.
 *
 * Optional override before this script:
 *   window.BACHATA_API_BASE = 'https://bachatabakery.com/cinnamon-roll-order-form';
 */
(function () {
  'use strict';

  var PHONE_DIGITS = /^1?\d{10}$/;
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var LOW_STOCK = 15;

  var state = {
    menu: null,
    qty: {},
    payments: null,
    paymentRequest: null,
    card: null,
    walletsReady: false,
    submitting: false,
    captcha: { siteKey: '', widgetId: null, token: null },
  };

  function $(id) {
    return document.getElementById(id);
  }

  function apiBase() {
    if (typeof window.BACHATA_API_BASE === 'string' && window.BACHATA_API_BASE) {
      return window.BACHATA_API_BASE.replace(/\/$/, '');
    }
    var match = (window.location.pathname || '/').match(/^(\/bachata-bakery(?=\/|$))/);
    return (match ? match[1] : '') + '/cinnamon-roll-order-form';
  }

  function dollars(cents) {
    return '$' + (cents / 100).toFixed(2);
  }

  function uuid() {
    if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    var b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    var h = Array.prototype.map.call(b, function (x) { return (x + 0x100).toString(16).slice(1); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
  }

  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'class') node.className = attrs[k];
      else node.setAttribute(k, attrs[k]);
    });
    if (text != null) node.textContent = text;
    return node;
  }

  async function getJson(path) {
    var res = await fetch(apiBase() + path, { cache: 'no-store', credentials: 'same-origin' });
    if (!res.ok) throw new Error(path + ' → HTTP ' + res.status);
    return res.json();
  }

  /* ---------- Menu ---------- */

  function renderMenu() {
    var wrap = $('menu-groups');
    wrap.textContent = '';
    wrap.removeAttribute('aria-busy');

    var groups = {};
    state.menu.items.forEach(function (item) {
      (groups[item.category] = groups[item.category] || []).push(item);
      state.qty[item.id] = 0;
    });

    Object.keys(groups).forEach(function (category) {
      var group = el('div', { class: 'no-menu-group' });
      group.appendChild(el('h3', { class: 'no-menu-heading' }, category));

      groups[category].forEach(function (item) {
        var row = el('div', { class: 'no-item', 'data-item': item.id });
        var info = el('div', { class: 'no-item-info' });
        info.appendChild(el('p', { class: 'no-item-name' }, item.name));
        info.appendChild(el('p', { class: 'no-item-desc' }, item.description));
        info.appendChild(el('p', { class: 'no-item-price' }, dollars(item.price_cents)));

        var stepper = el('div', { class: 'no-stepper', role: 'group', 'aria-label': 'Quantity of ' + item.name });
        var minus = el('button', { type: 'button', class: 'no-step', 'data-step': '-1', 'aria-label': 'Remove one ' + item.name }, '−');
        var count = el('span', { class: 'no-qty', 'aria-live': 'polite', id: 'qty-' + item.id }, '0');
        var plus = el('button', { type: 'button', class: 'no-step', 'data-step': '1', 'aria-label': 'Add one ' + item.name }, '+');
        minus.disabled = true;
        stepper.append(minus, count, plus);

        row.append(info, stepper);
        group.appendChild(row);
      });

      wrap.appendChild(group);
    });

    wrap.addEventListener('click', function (e) {
      var btn = e.target.closest('.no-step');
      if (!btn) return;
      var id = btn.closest('.no-item').getAttribute('data-item');
      setQty(id, state.qty[id] + Number(btn.getAttribute('data-step')));
    });

    renderSteppers();
  }

  function itemsInCart(exceptId) {
    return Object.keys(state.qty).reduce(function (sum, id) {
      return id === exceptId ? sum : sum + state.qty[id];
    }, 0);
  }

  /** Items still available this week across all products (sell-out limit). */
  function itemsRemaining() {
    var left = state.menu.items_remaining;
    return typeof left === 'number' ? left : Infinity;
  }

  function setQty(id, value) {
    var max = Math.min(state.menu.max_qty_per_item, itemsRemaining() - itemsInCart(id));
    state.qty[id] = Math.max(0, Math.min(max, value));
    $('items-error').textContent = '';
    renderSteppers();
    renderSummary();
  }

  /** Drops quantities (last items first) until the cart fits what's left. */
  function clampCart() {
    var over = itemsInCart() - itemsRemaining();
    for (var i = state.menu.items.length - 1; i >= 0 && over > 0; i--) {
      var id = state.menu.items[i].id;
      var take = Math.min(state.qty[id], over);
      state.qty[id] -= take;
      over -= take;
    }
  }

  function renderSteppers() {
    var full = itemsInCart() >= itemsRemaining();
    state.menu.items.forEach(function (item) {
      var qty = state.qty[item.id];
      var row = document.querySelector('.no-item[data-item="' + item.id + '"]');
      row.querySelector('.no-qty').textContent = String(qty);
      row.querySelector('[data-step="-1"]').disabled = qty === 0;
      row.querySelector('[data-step="1"]').disabled = full || qty >= state.menu.max_qty_per_item;
      row.classList.toggle('is-selected', qty > 0);
    });

    var note = $('stock-note');
    var left = itemsRemaining();
    note.hidden = left > LOW_STOCK;
    if (!note.hidden) {
      note.textContent = 'Only ' + left + (left === 1 ? ' treat' : ' treats') + ' left this week!' +
        (full && left > 0 ? ' Your order has the last of them.' : '');
    }
  }

  function orderLines() {
    return state.menu.items
      .filter(function (item) { return state.qty[item.id] > 0; })
      .map(function (item) {
        var qty = state.qty[item.id];
        return { id: item.id, name: item.name, qty: qty, line_cents: qty * item.price_cents };
      });
  }

  function totalCents() {
    return orderLines().reduce(function (sum, l) { return sum + l.line_cents; }, 0);
  }

  function renderSummary() {
    var lines = orderLines();
    var total = totalCents();
    var list = $('summary-lines');
    list.textContent = '';

    if (!lines.length) {
      list.appendChild(el('li', { class: 'no-summary-empty' }, 'No items yet.'));
    }
    lines.forEach(function (l) {
      var li = el('li');
      li.append(el('span', null, l.qty + ' × ' + l.name), el('span', null, dollars(l.line_cents)));
      list.appendChild(li);
    });

    $('summary-total').textContent = dollars(total);
    $('pay-btn').textContent = 'Pay ' + dollars(total);
    $('pay-btn').disabled = state.submitting || total === 0 || !state.card;

    if (state.paymentRequest && total > 0) {
      state.paymentRequest.update({ total: { amount: (total / 100).toFixed(2), label: 'Total' } });
    }
    if (total > 0 && !state.walletsReady && state.payments) {
      initWallets();
    }
  }

  /* ---------- Pickup ---------- */

  function renderPickup() {
    var wrap = $('pickup-options');
    state.menu.pickup.forEach(function (opt) {
      var item = el('div', { class: 'radio-card' });
      var input = el('input', { type: 'radio', name: 'pickup_location', id: 'pickup-' + opt.id, value: opt.id });
      var label = el('label', { for: 'pickup-' + opt.id });
      label.append(
        document.createTextNode(opt.label + ' — '),
        el('strong', null, opt.date_label)
      );
      item.append(input, label);
      wrap.appendChild(item);
    });

    wrap.addEventListener('change', function () {
      var opt = selectedPickup();
      var select = $('no_time');
      select.textContent = '';
      select.appendChild(el('option', { value: '' }, 'Choose a time'));
      opt.slots.forEach(function (slot) {
        select.appendChild(el('option', { value: slot }, slotLabel(slot)));
      });
      select.disabled = false;
      clearError(null, 'pickup-error');
    });
  }

  function selectedPickup() {
    var checked = document.querySelector('input[name="pickup_location"]:checked');
    if (!checked) return null;
    return state.menu.pickup.find(function (p) { return p.id === checked.value; }) || null;
  }

  function slotLabel(slot) {
    var parts = slot.split(':').map(Number);
    var h = parts[0] % 12 === 0 ? 12 : parts[0] % 12;
    return h + ':' + String(parts[1]).padStart(2, '0') + (parts[0] >= 12 ? ' PM' : ' AM');
  }

  /* ---------- Validation ---------- */

  function setError(input, errId, msg) {
    $(errId).textContent = msg;
    if (input) input.setAttribute('aria-invalid', 'true');
  }

  function clearError(input, errId) {
    $(errId).textContent = '';
    if (input) input.removeAttribute('aria-invalid');
  }

  /** Returns the first invalid element (to focus), or null when valid. */
  function validate() {
    var first = null;
    function fail(input, errId, msg, focusTarget) {
      setError(input, errId, msg);
      if (!first) first = focusTarget || input;
    }

    if (!orderLines().length) {
      fail(null, 'items-error', 'Please add at least one item.', document.querySelector('.no-step[data-step="1"]'));
    }

    [['no_first', 'Please enter your first name.'], ['no_last', 'Please enter your last name.']].forEach(function (f) {
      var input = $(f[0]);
      if (!input.value.trim()) fail(input, f[0] + '-error', f[1]);
      else clearError(input, f[0] + '-error');
    });

    var email = $('no_email');
    if (!EMAIL.test(email.value.trim())) fail(email, 'no_email-error', 'Please enter a valid email address.');
    else clearError(email, 'no_email-error');

    var phone = $('no_phone');
    if (!PHONE_DIGITS.test(phone.value.replace(/\D/g, ''))) fail(phone, 'no_phone-error', 'Please enter a 10-digit phone number.');
    else clearError(phone, 'no_phone-error');

    if (!selectedPickup()) {
      fail(null, 'pickup-error', 'Please choose a pickup location.', document.querySelector('input[name="pickup_location"]'));
    }

    var time = $('no_time');
    if (selectedPickup() && !time.value) fail(time, 'no_time-error', 'Please choose a pickup time.');
    else clearError(time, 'no_time-error');

    if (state.captcha.siteKey && !state.captcha.token) {
      fail(null, 'captcha-error', 'Please complete the “I am human” check.', $('captcha-wrap'));
    }

    return first;
  }

  /* ---------- hCaptcha (card-testing protection) ---------- */

  function loadCaptcha(siteKey) {
    state.captcha.siteKey = siteKey;
    $('captcha-wrap').hidden = false;

    window.bbCaptchaReady = function () {
      state.captcha.widgetId = window.hcaptcha.render('captcha-container', {
        sitekey: siteKey,
        callback: function (token) {
          state.captcha.token = token;
          clearError(null, 'captcha-error');
        },
        'expired-callback': function () { state.captcha.token = null; },
        'error-callback': function () { state.captcha.token = null; },
      });
    };

    var s = document.createElement('script');
    s.src = 'https://js.hcaptcha.com/1/api.js?render=explicit&onload=bbCaptchaReady&recaptchacompat=off';
    s.async = true;
    s.onerror = function () {
      setError(null, 'captcha-error', 'The “I am human” check couldn’t load. Please disable ad blockers for this page and refresh.');
    };
    document.head.appendChild(s);
  }

  /** Tokens are single-use: get a fresh one after every checkout attempt. */
  function resetCaptcha() {
    state.captcha.token = null;
    if (window.hcaptcha && state.captcha.widgetId !== null) window.hcaptcha.reset(state.captcha.widgetId);
  }

  /* ---------- Availability (sell-out limit / closed) ---------- */

  async function refreshAvailability() {
    try {
      var menu = await getJson('/api/menu.php');
      state.menu.open = menu.open;
      state.menu.items_remaining = menu.items_remaining;
      clampCart();
      renderSteppers();
      renderSummary();
      if (!menu.open && window.BachataPreorder) window.BachataPreorder.fetchStatus();
    } catch (err) { /* keep the current view */ }
  }

  /* ---------- Square ---------- */

  function loadSdk(env) {
    return new Promise(function (resolve, reject) {
      if (window.Square) return resolve();
      var s = document.createElement('script');
      s.src = env === 'production'
        ? 'https://web.squarecdn.com/v1/square.js'
        : 'https://sandbox.web.squarecdn.com/v1/square.js';
      s.onload = resolve;
      s.onerror = function () { reject(new Error('Could not load Square')); };
      document.head.appendChild(s);
    });
  }

  async function initSquare(config) {
    await loadSdk(config.app_env);
    state.payments = window.Square.payments(config.square_app_id, config.square_location_id);

    state.card = await state.payments.card({
      style: {
        '.input-container': { borderColor: '#D5BFA4', borderRadius: '0px' },
        '.input-container.is-focus': { borderColor: '#6A0513' },
        '.input-container.is-error': { borderColor: '#B3261E' },
        '.message-text': { color: '#5A3A2E' },
        '.message-icon': { color: '#6A0513' },
        input: { backgroundColor: '#FEF7E7', color: '#1F0D08' },
      },
    });
    $('card-container').textContent = '';
    await state.card.attach('#card-container');
    renderSummary();
  }

  /** Wallets need a non-zero total, so they are set up after the first item is added. */
  async function initWallets() {
    state.walletsReady = true;
    var total = (totalCents() / 100).toFixed(2);
    state.paymentRequest = state.payments.paymentRequest({
      countryCode: 'US',
      currencyCode: 'USD',
      total: { amount: total, label: 'Total' },
    });

    var shown = 0;

    try {
      var applePay = await state.payments.applePay(state.paymentRequest);
      var appleBtn = $('apple-pay-button');
      appleBtn.hidden = false;
      var startApple = function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        pay(function () { return tokenize(applePay); });
      };
      appleBtn.addEventListener('click', startApple);
      appleBtn.addEventListener('keydown', startApple);
      shown++;
    } catch (err) { /* Apple Pay unavailable on this device/browser */ }

    try {
      var googlePay = await state.payments.googlePay(state.paymentRequest);
      $('google-pay-button').hidden = false;
      await googlePay.attach('#google-pay-button', { buttonColor: 'black', buttonSizeMode: 'fill', buttonType: 'long' });
      $('google-pay-button').addEventListener('click', function (e) {
        e.preventDefault();
        pay(function () { return tokenize(googlePay); });
      });
      shown++;
    } catch (err) {
      $('google-pay-button').hidden = true;
    }

    try {
      var cashAppPay = await state.payments.cashAppPay(state.paymentRequest, {
        redirectURL: window.location.href,
        referenceId: 'bb-preorder',
      });
      $('cash-app-pay').hidden = false;
      await cashAppPay.attach('#cash-app-pay', { shape: 'semiround', width: 'full' });
      cashAppPay.addEventListener('ontokenization', function (event) {
        var detail = event.detail || {};
        if (detail.error || !detail.tokenResult || detail.tokenResult.status !== 'OK') {
          showStatus('Cash App Pay was cancelled or failed. Please try again.', true);
          return;
        }
        pay(function () { return Promise.resolve(detail.tokenResult.token); });
      });
      shown++;
    } catch (err) {
      $('cash-app-pay').hidden = true;
    }

    $('wallets').hidden = shown === 0;
  }

  async function tokenize(method) {
    var result = await method.tokenize();
    if (result.status === 'OK') return result.token;
    if (result.status !== 'Cancel') {
      var msg = (result.errors || []).map(function (e) { return e.message; }).join(' ');
      showStatus(msg || 'Please check your payment details and try again.', true);
    }
    return null;
  }

  /* ---------- Submit ---------- */

  function showStatus(msg, isError) {
    var s = $('form-status');
    s.textContent = msg || '';
    s.classList.toggle('is-error', !!isError);
  }

  function setSubmitting(on) {
    state.submitting = on;
    $('native-order-form').setAttribute('aria-busy', String(on));
    renderSummary();
    if (on) $('pay-btn').textContent = 'Processing…';
  }

  /**
   * getToken runs inside the click handler's call stack so Apple Pay keeps
   * its user-gesture requirement; validation before it is synchronous.
   */
  async function pay(getToken) {
    if (state.submitting) return;
    showStatus('');

    var invalid = validate();
    if (invalid) {
      invalid.focus();
      showStatus('Please fix the highlighted fields.', true);
      return;
    }

    setSubmitting(true);
    var sent = false;
    try {
      var token = await getToken();
      if (!token) return;

      showStatus('Placing your order…');
      var pickup = selectedPickup();
      sent = true;
      var res = await fetch(apiBase() + '/api/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          token: token,
          idempotency_key: uuid(),
          contact: {
            first: $('no_first').value.trim(),
            last: $('no_last').value.trim(),
            email: $('no_email').value.trim(),
            phone: $('no_phone').value.trim(),
          },
          pickup: { location: pickup.id, time: $('no_time').value },
          items: orderLines().map(function (l) { return { id: l.id, qty: l.qty }; }),
          amount_cents: totalCents(),
          captcha_token: state.captcha.token,
        }),
      });
      var data = await res.json().catch(function () { return {}; });

      if (data.success) {
        showConfirmation(data);
        return;
      }
      showStatus(data.message || 'Something went wrong. You have not been charged.', true);
      if (data.error_code === 'sold_out' || data.error_code === 'form_closed') refreshAvailability();
      if (data.error_code === 'captcha_failed') $('captcha-wrap').focus();
    } catch (err) {
      showStatus('We couldn’t reach the payment server. You have not been charged — please try again.', true);
    } finally {
      setSubmitting(false);
      if (sent && $('order-confirmation').hidden) resetCaptcha();
    }
  }

  function showConfirmation(data) {
    $('confirm-name').textContent = $('no_first').value.trim();
    $('confirm-ref').textContent = data.reference_id;
    $('confirm-email').textContent = $('no_email').value.trim();
    $('confirm-total').textContent = dollars(data.total_cents);
    $('confirm-pickup').textContent =
      data.pickup.label + ' — ' + data.pickup.date_label + ' at ' + data.pickup.time_label;

    var list = $('confirm-lines');
    list.textContent = '';
    data.items.forEach(function (l) {
      var li = el('li');
      li.append(el('span', null, l.qty + ' × ' + l.name), el('span', null, dollars(l.line_cents)));
      list.appendChild(li);
    });

    var receipt = $('confirm-receipt');
    if (data.receipt_url) receipt.href = data.receipt_url;
    else receipt.parentElement.hidden = true;

    $('presale-form').hidden = true;
    $('presale-open-callout').hidden = true;
    var box = $('order-confirmation');
    box.hidden = false;
    box.focus();
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ---------- Init ---------- */

  function bindForm() {
    $('native-order-form').addEventListener('submit', function (e) {
      e.preventDefault();
      pay(function () { return tokenize(state.card); });
    });

    $('native-order-form').addEventListener('input', function (e) {
      var errEl = e.target.id && $(e.target.id + '-error');
      if (errEl) clearError(e.target, e.target.id + '-error');
    });
  }

  async function init() {
    bindForm();

    var menu, config;
    try {
      [menu, config] = await Promise.all([getJson('/api/menu.php'), getJson('/api/config.php')]);
    } catch (err) {
      console.error('[BachataBakery] order form setup failed:', err);
      $('menu-groups').textContent = 'The order form is unavailable right now. Please refresh or email info@bachatabakery.com.';
      $('card-container').textContent = '';
      return;
    }

    state.menu = menu;
    renderMenu();
    renderPickup();
    renderSummary();

    if (config.app_env !== 'production') $('sandbox-banner').hidden = false;

    if (!menu.open) {
      // status.php (preorder-window.js) shows the closed / paused / sold-out message.
      if (window.BachataPreorder) window.BachataPreorder.fetchStatus();
      return;
    }

    if (config.hcaptcha_site_key) loadCaptcha(config.hcaptcha_site_key);

    try {
      await initSquare(config);
    } catch (err) {
      console.error('[BachataBakery] Square setup failed:', err);
      $('card-container').textContent = 'Payment form unavailable. Please refresh the page.';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
