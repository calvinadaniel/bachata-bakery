import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
import test from 'node:test';
import {
  parseBase,
  expectsNativeBooking,
  bookInView,
  checkSlug,
  renderReport,
  exitCode,
  sameDocument,
} from './post-deploy-smoke-lib.mjs';

test('parseBase requires an http(s) --base and returns the origin', () => {
  assert.equal(parseBase(['--base', 'https://bachatabakery.com/']), 'https://bachatabakery.com');
  assert.equal(parseBase(['--base', 'https://dev.bachatabakery.com/order/']), 'https://dev.bachatabakery.com');
  assert.throws(() => parseBase([]), (err) => err.code === 2);
  assert.throws(() => parseBase(['--base', 'ftp://bachatabakery.com']), (err) => err.code === 2);
});

test('expectsNativeBooking matches the page host swap', () => {
  assert.equal(expectsNativeBooking('https://dev.bachatabakery.com'), true);
  assert.equal(expectsNativeBooking('http://localhost:8080'), true);
  assert.equal(expectsNativeBooking('http://127.0.0.1:8080'), true);
  assert.equal(expectsNativeBooking('https://bachatabakery.com'), false);
});

test('bookInView uses the spec window', () => {
  assert.equal(bookInView(121, 800), true);
  assert.equal(bookInView(-40, 800), false);
  assert.equal(bookInView(-39, 800), true);
  assert.equal(bookInView(440, 800), false);
  assert.equal(bookInView(439, 800), true);
});

test('checkSlug is filesystem-safe', () => {
  assert.equal(checkSlug('Book your seat 1280'), 'book-your-seat-1280');
  assert.equal(checkSlug('  Tres Leches & Cinnamon  '), 'tres-leches-cinnamon');
});

test('renderReport lists pass and fail lines and the runner error', () => {
  const md = renderReport({
    base: 'https://bachatabakery.com',
    time: '2026-11-16T17:00:00.000Z',
    runnerError: '',
    checks: [
      { name: 'Book your seat', viewport: '1280x800', pass: true, reason: '' },
      { name: 'Book your seat', viewport: '390x844', pass: false, reason: '#book not in view' },
    ],
  });
  assert.match(md, /Base: https:\/\/bachatabakery\.com/);
  assert.match(md, /2026-11-16T17:00:00.000Z/);
  assert.match(md, /PASS Book your seat \(1280x800\)/);
  assert.match(md, /FAIL Book your seat \(390x844\) — #book not in view/);
});

test('exitCode is 2 for a runner error, 1 for a failed check, 0 when all pass', () => {
  assert.equal(exitCode({ runnerError: 'browser missing', checks: [] }), 2);
  assert.equal(exitCode({
    runnerError: 'blew up',
    checks: [{ name: 'x', viewport: '1280x800', pass: false, reason: 'nope' }],
  }), 2);
  assert.equal(exitCode({
    runnerError: '',
    checks: [{ name: 'x', viewport: '1280x800', pass: false, reason: 'nope' }],
  }), 1);
  assert.equal(exitCode({
    runnerError: '',
    checks: [{ name: 'x', viewport: '1280x800', pass: true, reason: '' }],
  }), 0);
});

test('sameDocument ignores the hash and requires origin, pathname, and search', () => {
  assert.equal(
    sameDocument(
      'https://bachatabakery.com/cookie-class/#book',
      'https://bachatabakery.com/cookie-class/',
    ),
    true,
  );
  assert.equal(
    sameDocument(
      'https://bachatabakery.com/cookie-class/?class=a',
      'https://bachatabakery.com/cookie-class/?class=b',
    ),
    false,
  );
  assert.equal(
    sameDocument(
      'https://bachatabakery.com/cookie-class/',
      'https://bachatabakery.com/order/',
    ),
    false,
  );
  assert.equal(
    sameDocument(
      'https://dev.bachatabakery.com/cookie-class/',
      'https://bachatabakery.com/cookie-class/',
    ),
    false,
  );
});

test('runner does not submit forms or wait for network idle', () => {
  const src = readFileSync(new URL('./post-deploy-smoke.mjs', import.meta.url), 'utf8');
  assert.doesNotMatch(src, /\.fill\(/);
  assert.doesNotMatch(src, /networkidle/);
  assert.doesNotMatch(src, /presale-continue-btn/);
  assert.doesNotMatch(src, /submit\.jotform/);
  assert.match(src, /expectsNativeBooking/);
  assert.match(src, /JotFormIFrame-252817790661061/);
});
