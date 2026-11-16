<#
.SYNOPSIS
  Upload Bachata Bakery's public site files to Hostinger using the Hostinger CLI.

.DESCRIPTION
  Uploads each file into public_html (or public_html/dev for -Target dev) over TUS, overwriting
  the server copy, clears the Hostinger cache/CDN, then checks every page returns 200.

  Only allowlisted public paths can be uploaded; anything that looks like a secret is refused.
  With no -Files, the default set (pages, CSS/JS, logos, .htaccess, robots/sitemap/llms) is uploaded.
  Photos and video are never uploaded by default; pass them explicitly with -Files.
  The native order page and Apple Pay domain file are opt-in too:
    -Files order-demo/index.html, assets/js/order-native.js, .well-known/apple-developer-merchantid-domain-association
  The PHP order backend deploys separately with scripts/deploy-backend.ps1.

  -Target dev uploads to dev.bachatabakery.com with scripts/dev.htaccess as its .htaccess (noindex,
  and missing assets are served from production), and skips robots/sitemap/llms.

  Requires the `hostinger` CLI with an API token in ~/.hostinger.yaml or HOSTINGER_API_TOKEN.

.EXAMPLE
  ./scripts/deploy.ps1 -DryRun
.EXAMPLE
  ./scripts/deploy.ps1 -Target dev
.EXAMPLE
  ./scripts/deploy.ps1 -Files .htaccess
.EXAMPLE
  ./scripts/deploy.ps1 -Files index.html, assets/videos/hero-bg-poster.jpg
#>
[CmdletBinding()]
param(
  [string[]]$Files,
  [ValidateSet('production', 'dev')]
  [string]$Target = 'production',
  [switch]$DryRun,
  [switch]$SkipCacheClear
)

$ErrorActionPreference = 'Stop'
$Username = 'u983304174'
$Domain   = 'bachatabakery.com'
$Root     = Split-Path -Parent $PSScriptRoot
$Pages    = @('', 'products/', 'order/', 'faq/', 'meet-the-baker/', 'presale/', 'cookie-class/')

$IsDev   = $Target -eq 'dev'
$Prefix  = if ($IsDev) { 'dev/' } else { '' }
$SiteUrl = if ($IsDev) { "https://dev.$Domain" } else { "https://$Domain" }
$LocalOverrides = if ($IsDev) { @{ '.htaccess' = 'scripts/dev.htaccess' } } else { @{} }

$DefaultFiles = @(
  'index.html',
  'products/index.html', 'order/index.html', 'faq/index.html',
  'meet-the-baker/index.html', 'presale/index.html', 'cookie-class/index.html',
  'styles.css', 'home.css', 'script.js',
  'assets/js/gsap.min.js', 'assets/js/ScrollTrigger.min.js', 'assets/js/ScrollSmoother.min.js', 'assets/js/motion.js',
  'assets/js/preorder-window.js', 'assets/js/featured-flavor.js',
  'assets/bb-logo-240.png', 'assets/bb-logo-480.png', 'assets/bb-favicon.png',
  'assets/bachata-bakery-red-logo-landscape.jpeg',
  'assets/cookie-class/warm-and-fuzzy.jpg', 'assets/cookie-class/pumpkin-patch.jpg',
  'assets/cookie-class/candyland.jpg', 'assets/cookie-class/candyland.webp',
  'assets/cookie-class/snuggles-snowflakes.jpg', 'assets/cookie-class/snuggles-snowflakes.webp',
  'assets/cookie-class/social-at-main-logo.png',
  '.htaccess'
)
if (-not $IsDev) { $DefaultFiles += @('robots.txt', 'sitemap.xml', 'llms.txt') }

$Allowed = '^(index\.html|(products|order|order-demo|faq|meet-the-baker|presale|cookie-class)/index\.html|\.well-known/apple-developer-merchantid-domain-association|styles\.css|home\.css|script\.js|\.htaccess|robots\.txt|sitemap\.xml|llms\.txt|google[0-9a-f]+\.html|[0-9a-f]{32}\.txt|assets/js/[^/]+\.js|assets/(.+/)?[^/]+\.(jpe?g|png|webp|avif|gif|svg|ico|mp4|webm))$'
$Blocked = '(?i)(password|passwd|secret|credential|token|\.env|finance|\.sql|\.bak|private)'

if (-not (Get-Command hostinger -ErrorAction SilentlyContinue)) {
  throw 'Hostinger CLI not found. Install it from https://github.com/hostinger/api-cli/releases'
}

# The CLI prints "Using config file" on stderr, which Windows PowerShell treats as fatal under 'Stop'.
function Invoke-Hostinger {
  $prev = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try { & hostinger @args 2>$null } finally { $ErrorActionPreference = $prev }
}

if (-not $Files -or $Files.Count -eq 0) { $Files = $DefaultFiles }
$Files = $Files | ForEach-Object { ($_ -replace '\\', '/') -replace '^\./', '' } | Select-Object -Unique

$plan = foreach ($rel in $Files) {
  if ($rel -match $Blocked)      { throw "Refusing to upload '$rel' (looks sensitive)." }
  if ($rel -notmatch $Allowed)   { throw "Refusing to upload '$rel' (not an allowlisted public path)." }
  $src  = if ($LocalOverrides.ContainsKey($rel)) { $LocalOverrides[$rel] } else { $rel }
  $full = Join-Path $Root $src
  if (-not (Test-Path -LiteralPath $full -PathType Leaf)) { throw "File not found: $src" }
  [pscustomobject]@{ Rel = $rel; Remote = "$Prefix$rel"; Src = $src; Full = $full; Size = (Get-Item -LiteralPath $full).Length }
}

Write-Host "Deploying $($plan.Count) file(s) to $SiteUrl (public_html/$Prefix):" -ForegroundColor Cyan
$plan | ForEach-Object {
  $from = if ($_.Src -ne $_.Rel) { "  (from $($_.Src))" } else { '' }
  Write-Host ("  {0,10:N0} B  {1}{2}" -f $_.Size, $_.Remote, $from)
}
if ($DryRun) { Write-Host 'Dry run - nothing uploaded.' -ForegroundColor Yellow; return }

$cred = Invoke-Hostinger hosting files generate-upload-url --username $Username --domain $Domain --format json | ConvertFrom-Json
if (-not $cred.url) { throw 'Could not get an upload URL from the Hostinger API.' }
$base = $cred.url.TrimEnd('/')

$failed = @()
foreach ($f in $plan) {
  $encoded = ($f.Remote -split '/' | ForEach-Object { [Uri]::EscapeDataString($_) }) -join '/'
  $uploadUrl = "$base/$encoded" + '?override=true'
  $auth    = @('-H', "X-Auth: $($cred.auth_key)", '-H', "X-Auth-Rest: $($cred.rest_auth_key)", '-H', 'Tus-Resumable: 1.0.0')

  $create = curl.exe -s -o NUL -w '%{http_code}' -X POST $uploadUrl @auth -H "Upload-Length: $($f.Size)" -H 'Upload-Offset: 0'
  if ($create -ne '201') { Write-Host "  FAIL create ($create)  $($f.Remote)" -ForegroundColor Red; $failed += $f.Remote; continue }

  if ($f.Size -gt 0) {
    $patch = curl.exe -s -o NUL -w '%{http_code}' -X PATCH $uploadUrl @auth -H 'Content-Type: application/offset+octet-stream' -H 'Upload-Offset: 0' --data-binary "@$($f.Full)"
    if ($patch -notmatch '^20[04]$') { Write-Host "  FAIL upload ($patch)  $($f.Remote)" -ForegroundColor Red; $failed += $f.Remote; continue }
  }
  Write-Host "  ok  $($f.Remote)" -ForegroundColor Green
}

if (-not $SkipCacheClear) {
  Invoke-Hostinger hosting cache clear-website $Username $Domain | Out-Null
  Write-Host 'Cache cleared.' -ForegroundColor Cyan
}

Write-Host 'Live check:' -ForegroundColor Cyan
$liveFailed = @()
foreach ($p in $Pages) {
  $code = curl.exe -s -o NUL -w '%{http_code}' "$SiteUrl/$p"
  $color = if ($code -eq '200') { 'Green' } else { 'Red' }
  Write-Host "  $code  $SiteUrl/$p" -ForegroundColor $color
  if ($code -ne '200') { $liveFailed += "$SiteUrl/$p ($code)" }
}

if ($failed.Count) { throw "Upload failed for: $($failed -join ', ')" }
if ($liveFailed.Count) { throw "Live check failed: $($liveFailed -join ', '). Smoke test skipped." }

Write-Host 'Smoke test:' -ForegroundColor Cyan
& node (Join-Path $PSScriptRoot 'post-deploy-smoke.mjs') --base $SiteUrl
if ($LASTEXITCODE -ne 0) {
  throw "Post-deploy smoke failed (exit $LASTEXITCODE). See tmp/deploy-smoke-last.md"
}
