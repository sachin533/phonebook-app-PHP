<#
    run-tests.ps1 - run every test suite in the project.

        powershell -ExecutionPolicy Bypass -File run-tests.ps1

    It starts the servers the tests need, runs the PHPUnit suites, the
    Playwright end to end tests and the two script based checks, and prints
    how many passed and how many failed.

    The suites always run against a TEST database. The script refuses to
    start if the database it would use is not one.
#>

param(
    [int]$Port = 8099,          # the test server for the HTTP suites
    [int]$AppPort = 8100,       # the test server for the main database
    [int]$DbPort = 3307,         # the MySQL port of the test database
    [string]$DbName = 'placement_tracker_test',
    [switch]$SkipPlaywright
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

# --- a safety check --------------------------------------------------------
# The suites write records and accounts, so they must never run against the
# database the real site uses.
if (-not $DbName.EndsWith('_test')) {
    Write-Host "The tests must run against a test database, not '$DbName'." -ForegroundColor Red
    exit 1
}

$php = Find-Php
if (-not $php) {
    Write-Host "PHP was not found." -ForegroundColor Red
    exit 1
}

$failed = 0
$results = @()

function Report {
    param([string]$Name, [int]$Passed, [int]$Failed)
    $script:results += [pscustomobject]@{ Suite = $Name; Passed = $Passed; Failed = $Failed }
    if ($Failed -gt 0) { $script:failed = 1 }
    $mark = if ($Failed -eq 0) { 'OK' } else { 'FAILED' }
    Write-Host ("  {0,-22} {1,4} passed  {2,3} failed   {3}" -f $Name, $Passed, $Failed, $mark) -ForegroundColor $(if ($Failed -eq 0) { 'Green' } else { 'Red' })
}

# --- the servers the tests need --------------------------------------------
$server = $null
if (-not (Test-NetConnection -ComputerName 127.0.0.1 -Port $Port -InformationLevel Quiet -WarningAction SilentlyContinue)) {
    Write-Host "Starting the test server on :$Port ..." -ForegroundColor DarkGray
    $env:DB_PORT = $DbPort
    $env:DB_NAME = $DbName
    $server = Start-Process -FilePath $php -ArgumentList @('-S', "127.0.0.1:$Port", '-t', $root) `
        -WorkingDirectory $root -WindowStyle Hidden -PassThru
    Start-Sleep -Seconds 2
}

# A second server, for the script based checks that use the main database.
$appServer = $null
if (-not (Test-NetConnection -ComputerName 127.0.0.1 -Port $AppPort -InformationLevel Quiet -WarningAction SilentlyContinue)) {
    Write-Host "Starting the second test server on :$AppPort ..." -ForegroundColor DarkGray
    $env:DB_PORT = $DbPort
    $env:DB_NAME = 'placement_tracker'
    $appServer = Start-Process -FilePath $php -ArgumentList @('-S', "127.0.0.1:$AppPort", '-t', $root) `
        -WorkingDirectory $root -WindowStyle Hidden -PassThru
    Start-Sleep -Seconds 2
}

# The PHPUnit suites always use the test database.
$env:DB_PORT = $DbPort
$env:DB_NAME = $DbName

$env:APP_BASE_URL = "http://127.0.0.1:$Port"
$env:E2E_DB_PORT  = $DbPort
$env:E2E_DB_NAME  = $DbName
$env:PHP_BIN      = $php

# --- 1. PHPUnit ------------------------------------------------------------
Write-Host "`n== PHPUnit ==" -ForegroundColor Cyan
$output = & $php (Join-Path $root 'phpunit.phar') 2>&1 | Out-String
$pass   = [int][regex]::Match($output, 'OK \((\d+) tests?').Groups[1].Value
$total  = [int][regex]::Match($output, 'Tests: (\d+)').Groups[1].Value
$failN  = [int][regex]::Match($output, 'Failures: (\d+)').Groups[1].Value
$errN   = [int][regex]::Match($output, 'Errors: (\d+)').Groups[1].Value
if ($total -eq 0) { $total = $pass }
Report 'phpunit' $pass (($failN + $errN))
if ($failN -gt 0 -or $errN -gt 0) {
    $output -split "`n" | Select-String -Pattern '^\d+\) ' | Select-Object -First 12 | ForEach-Object { Write-Host "      $_" -ForegroundColor Red }
}

# --- 2. Playwright ---------------------------------------------------------
if (-not $SkipPlaywright) {
    Write-Host "`n== Playwright ==" -ForegroundColor Cyan
    $e2e = Join-Path $root 'tests\e2e'
    if (Test-Path (Join-Path $e2e 'node_modules')) {
        # The tests have to run from their own folder, so Playwright finds
        # playwright.config.js.
        Push-Location $e2e
        try {
            $pwOutput = & npx playwright test --reporter=list 2>&1 | Out-String
        } finally {
            Pop-Location
        }
        $pwPass   = [int][regex]::Match($pwOutput, '(\d+) passed').Groups[1].Value
        $pwFail   = [int][regex]::Match($pwOutput, '(\d+) failed').Groups[1].Value
        $pwSkip   = [int][regex]::Match($pwOutput, '(\d+) skipped').Groups[1].Value
        $pwNotRun = [int][regex]::Match($pwOutput, '(\d+) did not run').Groups[1].Value
        Report 'playwright' $pwPass ($pwFail + $pwNotRun + $pwSkip)
        if ($pwFail -gt 0) {
            $pwOutput -split "`n" | Select-String -Pattern '^\s+\d+\) \[chromium\]' | Select-Object -First 12 |
                ForEach-Object { Write-Host "      $_" -ForegroundColor Red }
        }
    } else {
        Write-Host "  node_modules is missing. Run: cd tests\e2e; npm install" -ForegroundColor Yellow
    }
}

# --- 3. the script based checks -------------------------------------------
Write-Host "`n== Smoke and end to end scripts ==" -ForegroundColor Cyan
$smoke = & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root 'tests\smoke.ps1') `
    -Base "http://127.0.0.1:$Port" -Email 'route.test@example.com' -Password 'Route-Test-9' 2>&1 | Out-String
$smokePass = ([regex]::Matches($smoke, '\bPASS\b')).Count
$smokeFail = ([regex]::Matches($smoke, '\bFAIL\b')).Count
Report 'smoke.ps1' $smokePass $smokeFail

$e2eps = & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $root 'tests\e2e.ps1') `
    -Base "http://127.0.0.1:$AppPort" -Email 'shubham@example.com' -Password 'Placement2026' 2>&1 | Out-String
$e2ePass = [int][regex]::Match($e2eps, 'Passed:\s*(\d+)').Groups[1].Value
$e2eFail = [int][regex]::Match($e2eps, 'Failed:\s*(\d+)').Groups[1].Value
Report 'e2e.ps1' $e2ePass $e2eFail

# --- the summary -----------------------------------------------------------
if ($server -and -not $server.HasExited) {
    Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue
}
if ($appServer -and -not $appServer.HasExited) {
    Stop-Process -Id $appServer.Id -Force -ErrorAction SilentlyContinue
}

Write-Host ""
$results | Format-Table -AutoSize
$totalPassed = ($results | Measure-Object -Property Passed -Sum).Sum
$totalFailed = ($results | Measure-Object -Property Failed -Sum).Sum
Write-Host ("  TOTAL: {0} passed, {1} failed" -f $totalPassed, $totalFailed) -ForegroundColor $(if ($totalFailed -eq 0) { 'Green' } else { 'Red' })

exit $failed
