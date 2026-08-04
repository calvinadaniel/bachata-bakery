/**
 * Bachata Bakery — homepage weekly preorder window
 *
 * Prefer /api/status.php (same endpoint the order form polls). Falls back to a
 * client-side mirror of isFormOpen() / getNextOpenTime() / getClosesAt() when
 * the PHP API is unreachable (e.g. GitHub Pages without Hostinger).
 *
 * Timezone: America/New_York (BAKERY_TIMEZONE default).
 * Open: Sunday 00:00 – Wednesday 23:59:59. Closed: Thursday–Saturday.
 *
 * Optional override before this script:
 *   window.BACHATA_API_BASE = 'https://bachatabakery.com/cinnamon-roll-order-form';
 */
(function () {
  'use strict';

  var TZ = 'America/New_York';
  var CLOSE_COPY = 'Orders close Wednesday at 11:59 PM';

  function apiBase() {
    if (typeof window.BACHATA_API_BASE === 'string' && window.BACHATA_API_BASE) {
      return window.BACHATA_API_BASE.replace(/\/$/, '');
    }
    var path = window.location.pathname || '/';
    var match = path.match(/^(\/bachata-bakery(?=\/|$))/);
    var root = match ? match[1] : '';
    return root + '/cinnamon-roll-order-form';
  }

  function statusUrl() {
    return apiBase() + '/api/status.php';
  }

  function notifyUrl() {
    return apiBase() + '/api/notify.php';
  }

  /* ---------- Time helpers (mirror time_gate.php) ---------- */

  function bakeryParts(date) {
    var fmt = new Intl.DateTimeFormat('en-US', {
      timeZone: TZ,
      weekday: 'short',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23',
    });
    var parts = {};
    fmt.formatToParts(date).forEach(function (p) {
      if (p.type !== 'literal') parts[p.type] = p.value;
    });
    var weekdayMap = { Sun: 7, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
    return {
      dow: weekdayMap[parts.weekday],
      year: +parts.year,
      month: +parts.month,
      day: +parts.day,
      hour: +parts.hour,
      minute: +parts.minute,
      second: +parts.second,
    };
  }

  /** UTC Date for a bakery-local wall clock (handles EST/EDT). */
  function bakeryLocalToUtc(year, month, day, hour, minute, second) {
    hour = hour || 0;
    minute = minute || 0;
    second = second || 0;
    var guess = new Date(Date.UTC(year, month - 1, day, hour, minute, second));
    var p = bakeryParts(guess);
    var asUtc = Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute, p.second);
    var want = Date.UTC(year, month - 1, day, hour, minute, second);
    return new Date(guess.getTime() + (want - asUtc));
  }

  function addDaysYmd(y, m, d, delta) {
    var dt = new Date(Date.UTC(y, m - 1, d + delta));
    return { year: dt.getUTCFullYear(), month: dt.getUTCMonth() + 1, day: dt.getUTCDate() };
  }

  function isFormOpenLocal(now) {
    var dow = bakeryParts(now || new Date()).dow;
    return dow === 7 || dow <= 3;
  }

  function getClosesAtLocal(now) {
    var p = bakeryParts(now || new Date());
    var daysToWed = p.dow === 7 ? 3 : 3 - p.dow;
    var wed = addDaysYmd(p.year, p.month, p.day, daysToWed);
    return bakeryLocalToUtc(wed.year, wed.month, wed.day, 23, 59, 59);
  }

  function getNextOpenTimeLocal(now) {
    var p = bakeryParts(now || new Date());
    var daysUntilSunday = p.dow === 7 ? 7 : 7 - p.dow;
    var sun = addDaysYmd(p.year, p.month, p.day, daysUntilSunday);
    return bakeryLocalToUtc(sun.year, sun.month, sun.day, 0, 0, 0);
  }

  function localStatus() {
    var now = new Date();
    var open = isFormOpenLocal(now);
    var nextOpen = getNextOpenTimeLocal(now);
    var closesAt = getClosesAtLocal(now);
    return {
      open: open,
      next_open: nextOpen.toISOString(),
      closes_at: closesAt.toISOString(),
      reopens_at: nextOpen.toISOString(),
      closed_reason: open ? null : 'time_gate',
      timezone: TZ,
      _source: 'client',
    };
  }

  /* ---------- DOM / UI ---------- */

  var state = {
    open: null,
    closesAt: null,
    reopensAt: null,
    closedReason: null,
    source: 'pending',
  };
  var countdownTimer = null;

  function $(id) {
    return document.getElementById(id);
  }

  function applyStatus(data) {
    state.open = !!data.open;
    state.closesAt = data.closes_at ? new Date(data.closes_at) : null;
    state.reopensAt = data.reopens_at
      ? new Date(data.reopens_at)
      : data.next_open
        ? new Date(data.next_open)
        : null;
    state.closedReason = data.closed_reason || (state.open ? null : 'time_gate');
    state.source = data._source || 'api';
    render();
  }

  function pad(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function formatCountdown(ms) {
    if (ms <= 0) return { d: 0, h: 0, m: 0, s: 0 };
    var total = Math.floor(ms / 1000);
    var d = Math.floor(total / 86400);
    var h = Math.floor((total % 86400) / 3600);
    var m = Math.floor((total % 3600) / 60);
    var s = total % 60;
    return { d: d, h: h, m: m, s: s };
  }

  function tickCountdown() {
    var el = $('preorder-countdown');
    if (!el || !state.reopensAt) return;
    var diff = state.reopensAt.getTime() - Date.now();
    var t = formatCountdown(diff);
    el.textContent =
      'Opens in ' + t.d + 'd ' + pad(t.h) + 'h ' + pad(t.m) + 'm ' + pad(t.s) + 's';
    el.setAttribute('datetime', state.reopensAt.toISOString());
    if (diff <= 0) {
      applyStatus(localStatus());
      fetchStatus();
    }
  }

  function startCountdown() {
    stopCountdown();
    tickCountdown();
    countdownTimer = setInterval(tickCountdown, 1000);
  }

  function stopCountdown() {
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
  }

  function render() {
    var strip = $('preorder-strip');
    var cta = $('preorder-cta');
    var orderBtn = $('preorder-order-btn');
    var notifyBtn = $('preorder-notify-btn');
    var sub = $('preorder-cta-sub');
    var days = $('preorder-days');
    var windowSub = $('preorder-window-sub');
    var countdown = $('preorder-countdown');
    var headline = $('preorder-cta-headline');
    if (!strip || !cta || !orderBtn || !notifyBtn || !sub) return;

    strip.setAttribute('data-preorder-state', state.open ? 'open' : 'closed');
    strip.removeAttribute('aria-busy');

    if (state.open) {
      stopCountdown();
      if (days) days.textContent = 'Sun – Wed';
      if (windowSub) windowSub.textContent = 'Until sold out';
      if (headline) headline.hidden = true;
      if (countdown) {
        countdown.hidden = true;
        countdown.textContent = '';
      }
      orderBtn.hidden = false;
      notifyBtn.hidden = true;
      sub.hidden = false;
      sub.textContent = CLOSE_COPY;
      cta.classList.add('is-open');
      cta.classList.remove('is-closed');
    } else {
      var soldOut =
        state.closedReason === 'sold_out' || state.closedReason === 'force_closed';
      if (days) days.textContent = 'Closed';
      if (windowSub) {
        windowSub.textContent = soldOut ? 'Sold out this week' : 'Reopens Sunday';
      }
      if (headline) {
        headline.hidden = false;
        headline.textContent = soldOut ? 'Sold Out This Week' : 'Orders reopen Sunday';
      }
      if (countdown) countdown.hidden = false;
      orderBtn.hidden = true;
      notifyBtn.hidden = false;
      sub.hidden = true;
      cta.classList.add('is-closed');
      cta.classList.remove('is-open');
      startCountdown();
    }
  }

  /* ---------- Notify dialog ---------- */

  function openNotifyDialog() {
    var dialog = $('notify-dialog');
    if (!dialog) return;
    setNotifyStatus('');
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      dialog.setAttribute('open', '');
    }
    var email = $('notify-email');
    if (email) email.focus();
  }

  function closeNotifyDialog() {
    var dialog = $('notify-dialog');
    if (!dialog) return;
    if (typeof dialog.close === 'function') {
      dialog.close();
    } else {
      dialog.removeAttribute('open');
    }
  }

  function setNotifyStatus(msg, isError) {
    var el = $('notify-status');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('is-error', !!isError);
    el.hidden = !msg;
  }

  async function submitNotify(e) {
    e.preventDefault();
    var form = e.target;
    var email = ((form.email && form.email.value) || '').trim();
    var phone = ((form.phone && form.phone.value) || '').trim();
    var submitBtn = form.querySelector('[type="submit"]');

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setNotifyStatus('Please enter a valid email.', true);
      return;
    }

    setNotifyStatus('');
    if (submitBtn) submitBtn.disabled = true;

    var payload = { email: email, phone: phone, source: 'homepage' };
    var ok = false;

    try {
      var res = await fetch(notifyUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload),
      });
      if (res.ok) {
        var body = await res.json().catch(function () {
          return { ok: true };
        });
        ok = body.ok !== false;
      }
    } catch (err) {
      ok = false;
    }

    if (!ok) {
      var subject = encodeURIComponent('Notify me when weekly orders open');
      var bodyText = encodeURIComponent(
        'Please notify me when Bachata Bakery weekly preorders reopen.\n\nEmail: ' +
          email +
          (phone ? '\nPhone: ' + phone : '')
      );
      window.location.href =
        'mailto:info@bachatabakery.com?subject=' + subject + '&body=' + bodyText;
      setNotifyStatus('Opening your email app to finish signup…', false);
      if (submitBtn) submitBtn.disabled = false;
      return;
    }

    setNotifyStatus("You're on the list — we'll let you know when orders open.", false);
    form.reset();
    if (submitBtn) submitBtn.disabled = false;
    setTimeout(closeNotifyDialog, 1600);
  }

  /* ---------- Fetch + init ---------- */

  async function fetchStatus() {
    try {
      var res = await fetch(statusUrl(), { cache: 'no-store', credentials: 'omit' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var data = await res.json();
      if (typeof data.open !== 'boolean') throw new Error('bad payload');
      data._source = 'api';
      applyStatus(data);
      return true;
    } catch (err) {
      console.warn(
        '[BachataBakery] status API unavailable, using client time gate:',
        err && err.message ? err.message : err
      );
      return false;
    }
  }

  function bindEvents() {
    var notifyBtn = $('preorder-notify-btn');
    if (notifyBtn) {
      notifyBtn.addEventListener('click', function (e) {
        e.preventDefault();
        openNotifyDialog();
      });
    }

    var dialog = $('notify-dialog');
    if (!dialog) return;

    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) closeNotifyDialog();
    });
    var cancel = $('notify-cancel');
    if (cancel) cancel.addEventListener('click', closeNotifyDialog);
    var form = $('notify-form');
    if (form) form.addEventListener('submit', submitNotify);
  }

  function init() {
    // Immediate client mirror — avoids wrong-state flash before fetch resolves
    applyStatus(localStatus());
    bindEvents();
    fetchStatus();
    setInterval(function () {
      if (state.source === 'api') {
        fetchStatus();
      } else {
        applyStatus(localStatus());
      }
    }, 60_000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.BachataPreorder = {
    localStatus: localStatus,
    fetchStatus: fetchStatus,
    isFormOpen: isFormOpenLocal,
    getClosesAt: getClosesAtLocal,
    getNextOpenTime: getNextOpenTimeLocal,
  };
})();
