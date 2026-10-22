<#
    start.ps1 - bring the whole thing up in one command.

    It checks that MySQL is running, starts the web server if it is not,
    and then opens a Cloudflare Tunnel so the site can be reached from
    anywhere. The public address is printed and written to
    .tunnel-url.txt.

    Usage:
        powershell -ExecutionPolicy Bypass -File start.ps1
        powershell -ExecutionPolicy Bypass -File start.ps1 -Port 8080
        powershell -ExecutionPolicy Bypass -File start.ps1 -NoTunnel

    Press Ctrl + C to stop the tunnel and the web server.
#>

param(
    [int]$Port = 8000,
    [switch]$NoTunnel,
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path

function Find-Php {
    foreach ($name in @('php.exe', 'php')) {
        $cmd = Get-Command $name -ErrorAction SilentlyContinue
        if ($cmd) { return $cmd.Source }
    }
    $found = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter php.exe -Recurse -ErrorAction SilentlyContinue |
             Where-Object { $_.FullName -like '*PHP.PHP*' } | Select-Object -First 1
    if ($found) { return $found.FullName }
    return $null
}

function Find-Cloudflared {
    foreach ($name in @('cloudflared.exe', 'cloudflared')) {
        $cmd = Get-Command $name -ErrorAction SilentlyContinue
        if ($cmd) { return $cmd.Source }
    }
    foreach ($path in @(
        'C:\Program Files (x86)\cloudflared\cloudflared.exe',
        'C:\Program Files\cloudflared\cloudflared.exe'
    )) {
        if (Test-Path $path) { return $path }
    }
    return $null
}

# --- 1. MySQL --------------------------------------------------------------
$service = Get-Service MySQL80 -ErrorAction SilentlyContinue
if ($service) {
    if ($service.Status -ne 'Running') {
        Write-Host "Starting the MySQL80 service ..." -ForegroundColor Cyan
        try {
            Start-Service MySQL80
            Start-Sleep -Seconds 3
        } catch {
            Write-Host "Could not start MySQL80: $($_.Exception.Message)" -ForegroundColor Red
            Write-Host "Start it yourself with an elevated prompt:  Start-Service MySQL80" -ForegroundColor Yellow
        }
    } else {
        Write-Host "MySQL80 is running." -ForegroundColor DarkGray
    }
}

# --- 2. the web server -----------------------------------------------------
$php = Find-Php
if (-not $php) {
    Write-Host "PHP was not found. Install it with: winget install --id PHP.PHP.8.3" -ForegroundColor Red
    exit 1
}

$localUrl = "http://localhost:$Port"
$server = $null

$alreadyUp = $false
try {
    $response = Invoke-WebRequest -Uri $localUrl -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop
    $alreadyUp = $true
} catch {
    $alreadyUp = $false
}

if ($alreadyUp) {
    Write-Host "The site is already running on $localUrl." -ForegroundColor DarkGray
} else {
    Write-Host "Starting the web server on $localUrl ..." -ForegroundColor Cyan
    $server = Start-Process -FilePath $php `
        -ArgumentList @('-S', "localhost:$Port", '-t', $root) `
        -WorkingDirectory $root -WindowStyle Hidden -PassThru
    Start-Sleep -Seconds 2

    try {
        $check = Invoke-WebRequest -Uri $localUrl -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
        Write-Host "The site is up." -ForegroundColor Green
    } catch {
        Write-Host "The web server did not answer on $localUrl." -ForegroundColor Red
        exit 1
    }
}

# --- 3. the tunnel ---------------------------------------------------------
if ($NoTunnel) {
    Write-Host ""
    Write-Host "  Open $localUrl in your browser." -ForegroundColor White
    Write-Host ""
    if ($server) { Write-Host "The web server is running in the background (PID $($server.Id))." -ForegroundColor DarkGray }
    exit 0
}

$cloudflared = Find-Cloudflared
if (-not $cloudflared) {
    Write-Host ""
    Write-Host "  Open $localUrl in your browser." -ForegroundColor White
    Write-Host "  (cloudflared is not installed, so there is no public address.)" -ForegroundColor Yellow
    exit 0
}

Write-Host "Starting the Cloudflare Tunnel ..." -ForegroundColor Cyan
& (Join-Path $root 'tunnel.ps1') -Port $Port -NoBrowser:$NoBrowser
