<#
    tunnel.ps1 - put the local site on the internet with a Cloudflare Tunnel.

    A quick tunnel needs no account: it prints a public
    https://<something>.trycloudflare.com address that points at the local
    web server. The address changes every time the tunnel is restarted.

    Usage:
        powershell -ExecutionPolicy Bypass -File tunnel.ps1
        powershell -ExecutionPolicy Bypass -File tunnel.ps1 -Port 8080
        powershell -ExecutionPolicy Bypass -File tunnel.ps1 -NoBrowser

    A named tunnel (a stable address on your own domain) needs a Cloudflare
    account. Create it once with:
        cloudflared tunnel login
        cloudflared tunnel create placement-tracker
        cloudflared tunnel route dns placement-tracker tracker.example.com
    then run:
        powershell -ExecutionPolicy Bypass -File tunnel.ps1 -Named placement-tracker
#>

param(
    [int]$Port = 8000,
    [string]$Named = '',
    [switch]$NoBrowser,
    [switch]$NoStartServer
)

$ErrorActionPreference = 'Continue'

# --- find the tools --------------------------------------------------------
function Find-Cloudflared {
    $cmd = Get-Command cloudflared.exe -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $cmd = Get-Command cloudflared -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    foreach ($path in @(
        'C:\Program Files (x86)\cloudflared\cloudflared.exe',
        'C:\Program Files\cloudflared\cloudflared.exe',
        "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\Cloudflare.cloudflared*\cloudflared.exe"
    )) {
        $found = Get-Item $path -ErrorAction SilentlyContinue
        if ($found) { return $found[0].FullName }
    }
    return $null
}

function Find-Php {
    $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return $null
}

$cloudflared = Find-Cloudflared
if (-not $cloudflared) {
    Write-Host "cloudflared is not installed." -ForegroundColor Red
    Write-Host "Install it with:  winget install --id Cloudflare.cloudflared" -ForegroundColor Yellow
    exit 1
}

$php = Find-Php
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$localUrl = "http://localhost:$Port"

# --- is the site already running? ------------------------------------------
$siteUp = $false
try {
    $response = Invoke-WebRequest -Uri $localUrl -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop
    $siteUp = $response.StatusCode -lt 500
} catch {
    $siteUp = $false
}

$server = $null

if (-not $siteUp) {
    if ($NoStartServer) {
        Write-Host "Nothing is answering on $localUrl." -ForegroundColor Red
        exit 1
    }
    if (-not $php) {
        Write-Host "PHP was not found, and nothing is running on $localUrl." -ForegroundColor Red
        exit 1
    }

    Write-Host "Starting the web server on $localUrl ..." -ForegroundColor Cyan
    $server = Start-Process -FilePath $php `
        -ArgumentList @('-S', "localhost:$Port", '-t', $root) `
        -WorkingDirectory $root -WindowStyle Hidden -PassThru
    Start-Sleep -Seconds 2
} else {
    Write-Host "The site is already running on $localUrl." -ForegroundColor DarkGray
}

# --- start the tunnel ------------------------------------------------------
$logFile = Join-Path $env:TEMP ("cloudflared_" + [guid]::NewGuid().ToString('N') + ".log")

if ($Named) {
    Write-Host "Starting the named tunnel '$Named' ..." -ForegroundColor Cyan
    $tunnel = Start-Process -FilePath $cloudflared `
        -ArgumentList @('tunnel', '--no-autoupdate', 'run', $Named) `
        -RedirectStandardOutput $logFile -RedirectStandardError "$logFile.err" -PassThru -WindowStyle Hidden
} else {
    Write-Host "Starting a quick tunnel ..." -ForegroundColor Cyan
    $tunnel = Start-Process -FilePath $cloudflared `
        -ArgumentList @('tunnel', '--no-autoupdate', '--url', $localUrl) `
        -RedirectStandardOutput $logFile -RedirectStandardError "$logFile.err" -PassThru -WindowStyle Hidden
}

# --- wait for the public address -------------------------------------------
# cloudflared writes its log to standard error, so both streams are read.
$publicUrl = $null
$deadline = (Get-Date).AddSeconds(60)

while ((Get-Date) -lt $deadline -and -not $publicUrl) {
    Start-Sleep -Milliseconds 800

    $text = ''
    foreach ($file in @($logFile, "$logFile.err")) {
        if (Test-Path $file) {
            $text += (Get-Content $file -Raw -ErrorAction SilentlyContinue)
        }
    }

    if ($text -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
        $publicUrl = $matches[0]
    }

    if ($tunnel.HasExited) {
        Write-Host "The tunnel stopped. Here is what it said:" -ForegroundColor Red
        Get-Content $logFile, "$logFile.err" -ErrorAction SilentlyContinue | Select-Object -Last 20
        exit 1
    }
}

Write-Host ""
if ($publicUrl) {
    Write-Host "  Your site is live at:" -ForegroundColor Green
    Write-Host ""
    Write-Host "      $publicUrl" -ForegroundColor White
    Write-Host ""
    Write-Host "  Pointing at  $localUrl" -ForegroundColor DarkGray
    Write-Host ""
    Write-Host "  IMPORTANT: the address only works while this script is running." -ForegroundColor Yellow
    Write-Host "  Press Ctrl + C to close the tunnel - the address stops working" -ForegroundColor Yellow
    Write-Host "  at that moment, and a new one is made the next time you start it." -ForegroundColor Yellow

    # Leave the address in a file, so it is easy to find again.
    $urlFile = Join-Path $root '.tunnel-url.txt'
    Set-Content -Path $urlFile -Value $publicUrl -Encoding ASCII
    Write-Host "  The address was also written to .tunnel-url.txt" -ForegroundColor DarkGray
    Write-Host ""

    if (-not $NoBrowser) {
        Start-Process $publicUrl
    }
} elseif ($Named) {
    Write-Host "  The named tunnel is running. The address is the one you set up" -ForegroundColor Green
    Write-Host "  with 'cloudflared tunnel route dns'." -ForegroundColor DarkGray
} else {
    Write-Host "  The tunnel is starting but no address has appeared yet." -ForegroundColor Yellow
    Write-Host "  Watch it here:  $logFile" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "  Press Ctrl+C to stop the tunnel." -ForegroundColor DarkGray
Write-Host ""

# --- keep going until the tunnel stops -------------------------------------
try {
    while (-not $tunnel.HasExited) {
        Start-Sleep -Seconds 1
    }
}
finally {
    if ($server -and -not $server.HasExited) {
        Write-Host "Stopping the web server ..." -ForegroundColor DarkGray
        Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue
    }
    if ($tunnel -and -not $tunnel.HasExited) {
        Stop-Process -Id $tunnel.Id -Force -ErrorAction SilentlyContinue
    }
    Remove-Item $logFile, "$logFile.err" -Force -ErrorAction SilentlyContinue
    Remove-Item (Join-Path $root '.tunnel-url.txt') -Force -ErrorAction SilentlyContinue
}
