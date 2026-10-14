<#
.SYNOPSIS
  Upload the order backend (cinnamon-roll-order-form/) to Hostinger using the Hostinger CLI.

.DESCRIPTION
  Uploads the PHP API, webhook and admin files into public_html/cinnamon-roll-order-form
  (or public_html/dev/cinnamon-roll-order-form for -Target dev) over TUS, then smoke-tests
  that the API answers and that private paths (.env, helpers, tests, schema) are refused.

  Only the files in $BackendFiles can be uploaded. Never uploaded: tests/, setup/, docs,
  schema.sql, .env.example, the retired api/order.php and its mailer/email templates.

  .env is uploaded ONLY when -EnvFile is given, and is checked first (without printing values):
    production → APP_ENV=production, a real hCaptcha secret, ADMIN_PASS 16+ chars
    dev        → APP_ENV=sandbox

  Requires the `hostinger` CLI with an API token in ~/.hostinger.yaml or HOSTINGER_API_TOKEN.

.EXAMPLE
  ./scripts/deploy-backend.ps1 -DryRun
.EXAMPLE
  ./scripts/deploy-backend.ps1 -Target dev -EnvFile C:\secure\bachata-dev.env
.EXAMPLE
  ./scripts/deploy-backend.ps1 -Files api/checkout.php
#>
[CmdletBinding()]
param(
  [string[]]$Files,
  [ValidateSet('production', 'dev')]
  [string]$Target = 'production',
  [string]$EnvFile,
  [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$Username   = 'u983304174'
$Domain     = 'bachatabakery.com'
$Root       = Join-Path (Split-Path -Parent $PSScriptRoot) 'cinnamon-roll-order-form'
$IsDev      = $Target -eq 'dev'
$RemoteBase = if ($IsDev) { 'dev/cinnamon-roll-order-form/' } else { 'cinnamon-roll-order-form/' }
$SiteUrl    = if ($IsDev) { "https://dev.$Domain/cinnamon-roll-order-form" } else { "https://$Domain/cinnamon-roll-order-form" }

$BackendFiles = @(
  '.htaccess',
  'api/checkout.php', 'api/config.php', 'api/menu.php', 'api/status.php', 'api/notify.php',
  'api/helpers/.htaccess', 'api/helpers/db.php', 'api/helpers/catalog.php', 'api/helpers/checkout_lib.php',
  'api/helpers/square.php', 'api/helpers/store.php', 'api/helpers/captcha.php', 'api/helpers/cap.php',
  'api/helpers/time_gate.php', 'api/helpers/webhook_lib.php',
  'api/webhook/.htaccess', 'api/webhook/square.php',
  'admin/index.php', 'admin/login.php', 'admin/logout.php', 'admin/auth.php', 'admin/window.php', 'admin/dashboard.php',
  'admin/actions/cap-override.php', 'admin/actions/force-close.php', 'admin/actions/pause.php', 'admin/actions/export-csv.php'
)

if (-not (Get-Command hostinger -ErrorAction SilentlyContinue)) {
  throw 'Hostinger CLI not found. Install it from https://github.com/hostinger/api-cli/releases'
}

# The CLI prints "Using config file" on stderr, which Windows PowerShell treats as fatal under 'Stop'.
function Invoke-Hostinger {
  $prev = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try { & hostinger @args 2>$null } finally { $ErrorActionPreference = $prev }
}

function Read-EnvKeys([string]$Path) {
  $map = @{}
  foreach ($line in Get-Content -LiteralPath $Path) {
    if ($line -match '^\s*#' -or $line -notmatch '=') { continue }
    $k, $v = $line -split '=', 2
    $v = ($v -split ' #', 2)[0].Trim().Trim('"', "'")
    $map[$k.Trim()] = $v
  }
  $map
}

function Assert-EnvFile([string]$Path) {
  if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "EnvFile not found: $Path" }
  $e = Read-EnvKeys $Path
  $problems = @()
  foreach ($k in 'SQUARE_APP_ID', 'SQUARE_LOCATION_ID', 'SQUARE_ACCESS_TOKEN', 'SQUARE_WEBHOOK_SIG_KEY', 'WEBHOOK_URL',
                 'DB_NAME', 'DB_USER', 'DB_PASS', 'HCAPTCHA_SITE_KEY', 'ADMIN_PASS') {
    if (-not $e[$k]) { $problems += "$k is empty" }
  }
  # Sandbox skips server-side verification when the secret is empty; production never does.
  if (-not $IsDev -and -not $e['HCAPTCHA_SECRET']) { $problems += 'HCAPTCHA_SECRET is empty' }
  foreach ($k in 'DB_NAME', 'DB_USER') {
    if ($e[$k] -and $e[$k] -notlike "${Username}_*") { $problems += "$k must be a Hostinger database ($($Username)_...), not a local one" }
  }
  if ($e['WEBHOOK_URL'] -and $e['WEBHOOK_URL'] -ne "$SiteUrl/api/webhook/square.php") {
    $problems += "WEBHOOK_URL must be $SiteUrl/api/webhook/square.php"
  }
  if ($IsDev) {
    if ($e['APP_ENV'] -ne 'sandbox') { $problems += 'APP_ENV must be sandbox for dev' }
  } else {
    if ($e['APP_ENV'] -ne 'production')                       { $problems += 'APP_ENV must be production' }
    if ($e['SQUARE_APP_ID'] -like 'sandbox-*')                { $problems += 'SQUARE_APP_ID is a sandbox id' }
    if ($e['HCAPTCHA_SECRET'] -match '^0x0+$')                { $problems += 'HCAPTCHA_SECRET is the public test secret' }
    if ("$($e['ADMIN_PASS'])".Length -lt 16 -or $e['ADMIN_PASS'] -eq 'changeme') { $problems += 'ADMIN_PASS must be 16+ characters' }
  }
  if ($problems.Count) { throw "EnvFile check failed:`n  - " + ($problems -join "`n  - ") }
}

if (-not $Files -or $Files.Count -eq 0) { $Files = $BackendFiles }
$Files = $Files | ForEach-Object { (($_ -replace '\\', '/') -replace '^\./', '') -replace '^cinnamon-roll-order-form/', '' } | Select-Object -Unique

$plan = foreach ($rel in $Files) {
  if ($BackendFiles -notcontains $rel) { throw "Refusing to upload '$rel' (not in the backend allowlist)." }
  $full = Join-Path $Root $rel
  if (-not (Test-Path -LiteralPath $full -PathType Leaf)) { throw "File not found: cinnamon-roll-order-form/$rel" }
  [pscustomobject]@{ Remote = "$RemoteBase$rel"; Full = $full; Size = (Get-Item -LiteralPath $full).Length; Secret = $false }
}

if ($EnvFile) {
  Assert-EnvFile $EnvFile
  $plan = @($plan) + [pscustomobject]@{ Remote = "$RemoteBase.env"; Full = (Resolve-Path -LiteralPath $EnvFile).Path; Size = (Get-Item -LiteralPath $EnvFile).Length; Secret = $true }
}

Write-Host "Deploying $(@($plan).Count) backend file(s) to $SiteUrl (public_html/$RemoteBase):" -ForegroundColor Cyan
$plan | ForEach-Object {
  $note = if ($_.Secret) { '  (from -EnvFile; checked)' } else { '' }
  Write-Host ("  {0,10:N0} B  {1}{2}" -f $_.Size, $_.Remote, $note)
}
if (-not $EnvFile) { Write-Host '  (.env not uploaded - pass -EnvFile to upload it)' -ForegroundColor DarkGray }
if ($DryRun) { Write-Host 'Dry run - nothing uploaded.' -ForegroundColor Yellow; return }

$cred = Invoke-Hostinger hosting files generate-upload-url --username $Username --domain $Domain --format json | ConvertFrom-Json
if (-not $cred.url) { throw 'Could not get an upload URL from the Hostinger API.' }
$base = $cred.url.TrimEnd('/')

$failed = @()
foreach ($f in $plan) {
  $encoded   = ($f.Remote -split '/' | ForEach-Object { [Uri]::EscapeDataString($_) }) -join '/'
  $uploadUrl = "$base/$encoded" + '?override=true'
  $auth      = @('-H', "X-Auth: $($cred.auth_key)", '-H', "X-Auth-Rest: $($cred.rest_auth_key)", '-H', 'Tus-Resumable: 1.0.0')

  $create = curl.exe -s -o NUL -w '%{http_code}' -X POST $uploadUrl @auth -H "Upload-Length: $($f.Size)" -H 'Upload-Offset: 0'
  if ($create -ne '201') { Write-Host "  FAIL create ($create)  $($f.Remote)" -ForegroundColor Red; $failed += $f.Remote; continue }

  if ($f.Size -gt 0) {
    $patch = curl.exe -s -o NUL -w '%{http_code}' -X PATCH $uploadUrl @auth -H 'Content-Type: application/offset+octet-stream' -H 'Upload-Offset: 0' --data-binary "@$($f.Full)"
    if ($patch -notmatch '^20[04]$') { Write-Host "  FAIL upload ($patch)  $($f.Remote)" -ForegroundColor Red; $failed += $f.Remote; continue }
  }
  Write-Host "  ok  $($f.Remote)" -ForegroundColor Green
}

Write-Host 'Smoke test:' -ForegroundColor Cyan
$checks = @(
  @{ Path = 'api/status.php';       Want = '^200$' },
  @{ Path = 'api/menu.php';         Want = '^200$' },
  @{ Path = 'admin/login.php';      Want = '^200$' },
  @{ Path = '.env';                 Want = '^(403|404)$' },
  @{ Path = '.env.example';         Want = '^(403|404)$' },
  @{ Path = 'schema.sql';           Want = '^(403|404)$' },
  @{ Path = 'api/helpers/db.php';   Want = '^(403|404)$' },
  @{ Path = 'tests/run.php';        Want = '^(403|404)$' },
  @{ Path = 'api/order.php';        Want = '^(403|404|410)$' },
  @{ Path = 'api/webhook/square.php'; Want = '^(403|405)$' }
)
$smokeFailed = 0
foreach ($c in $checks) {
  $code = curl.exe -s -o NUL -w '%{http_code}' "$SiteUrl/$($c.Path)"
  $ok = $code -match $c.Want
  if (-not $ok) { $smokeFailed++ }
  Write-Host ("  {0}  {1}  {2}" -f $(if ($ok) { 'ok  ' } else { 'FAIL' }), $code, $c.Path) -ForegroundColor $(if ($ok) { 'Green' } else { 'Red' })
}

if ($failed.Count) { throw "Upload failed for: $($failed -join ', ')" }
if ($smokeFailed) { throw "$smokeFailed smoke check(s) failed - see above. (status/menu need .env and the database.)" }
