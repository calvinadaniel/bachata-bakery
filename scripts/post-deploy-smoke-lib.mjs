export function parseBase(argv) {
  const i = argv.indexOf('--base');
  const raw = i === -1 ? '' : argv[i + 1];
  if (!raw) fail('Missing --base https://host');
  let url;
  try {
    url = new URL(raw);
  } catch {
    fail('Invalid --base URL');
  }
  if (url.protocol !== 'https:' && url.protocol !== 'http:') fail('Invalid --base URL');
  return url.origin;
}

function fail(message) {
  const err = new Error(message);
  err.code = 2;
  throw err;
}

export function expectsNativeBooking(base) {
  const host = new URL(base).hostname;
  return host === 'localhost' || host === '127.0.0.1' || host.startsWith('dev.');
}

export function bookInView(top, viewportHeight) {
  return top > -40 && top / viewportHeight < 0.55;
}

export function checkSlug(name) {
  return name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

export function renderReport({ base, time, checks, runnerError }) {
  const lines = [
    '# Post-deploy smoke',
    '',
    `- Base: ${base || '(none)'}`,
    `- Time: ${time}`,
    '',
  ];
  if (runnerError) {
    lines.push(`Runner error: ${runnerError}`, '');
  }
  for (const check of checks) {
    const status = check.pass ? 'PASS' : 'FAIL';
    const why = check.pass || !check.reason ? '' : ` — ${check.reason}`;
    lines.push(`- ${status} ${check.name} (${check.viewport})${why}`);
  }
  if (!checks.length && !runnerError) lines.push('- (no checks ran)');
  return `${lines.join('\n')}\n`;
}

export function exitCode({ runnerError, checks }) {
  if (runnerError) return 2;
  if (checks.some((check) => !check.pass)) return 1;
  return 0;
}

export function sameDocument(currentUrl, targetUrl) {
  const current = new URL(currentUrl);
  const target = new URL(targetUrl);
  return current.origin === target.origin
    && current.pathname === target.pathname
    && current.search === target.search;
}
