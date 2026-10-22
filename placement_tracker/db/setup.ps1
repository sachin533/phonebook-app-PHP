<#
    db\setup.ps1 - one-command database setup for the Placement Tracker.

    It does everything a fresh machine needs:

      1. creates the `placement_tracker` and `placement_tracker_test`
         databases with the `applications` and `users` tables
      2. brings an existing database up to the current schema
         (db\migrate.php) without touching a single row
      3. creates the least-privilege `tracker_app` MySQL account
      4. seeds the five demonstration rows
      5. creates the first application account

    Run it from the project root:

        powershell -ExecutionPolicy Bypass -File db\setup.ps1

    It asks for the MySQL root password and never stores it.
#>

param(
    # NOT $Host - that name is a read-only PowerShell automatic variable.
    [string]$DbHost  = '127.0.0.1',
    [int]   $Port    = 3306,
    [string]$MySqlExe = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe',
    # Leave empty to be asked for the root password securely.
    [string]$RootPassword = '',
    # Leave empty to be asked for the account details.
    [string]$UserName = '',
    [string]$UserEmail = '',
    [string]$UserUsername = '',
    [string]$UserPassword = ''
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path $MySqlExe)) {
    $cmd = Get-Command mysql.exe -ErrorAction SilentlyContinue
    if ($cmd) { $MySqlExe = $cmd.Source }
    else {
        Write-Host "mysql.exe not found. Pass -MySqlExe '<full path to mysql.exe>'." -ForegroundColor Red
        exit 1
    }
}

# --- find PHP --------------------------------------------------------------
# The PATH may not have been refreshed in this shell yet, so the usual
# places are checked as well.
function Find-Php {
    foreach ($candidate in @('php.exe', 'php')) {
        $found = Get-Command $candidate -ErrorAction SilentlyContinue
        if ($found) { return $found.Source }
    }

    $winget = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (Test-Path $winget) {
        $found = Get-ChildItem $winget -Filter php.exe -Recurse -ErrorAction SilentlyContinue |
                 Where-Object { $_.FullName -like '*PHP.PHP*' } |
                 Select-Object -First 1
        if ($found) { return $found.FullName }
    }

    foreach ($path in @('C:\php\php.exe', 'C:\xampp\php\php.exe')) {
        if (Test-Path $path) { return $path }
    }

    return $null
}

$phpExe = Find-Php

# Every PHP script this file runs has to talk to the SAME database as the
# script itself, so the port is passed on through the environment.
$env:DB_PORT = $Port
$env:DB_NAME = 'placement_tracker'

# --- the password the application will use ---------------------------------
# It must match DB_PASS in config.php.
$configFile = Join-Path $root 'config.php'
if (-not (Test-Path $configFile)) {
    Write-Host "config.php is missing. Copy config.example.php to config.php first." -ForegroundColor Red
    exit 1
}

# config.php is PHP, so read the value with PHP when it is available and
# fall back to a simple text scan otherwise.
$appPass = $null
if ($phpExe) {
    $appPass = & $phpExe -r "`$c = require '$($configFile -replace '\\','/')'; echo `$c['DB_PASS'] ?? '';" 2>$null
}
if (-not $appPass) {
    $raw = Get-Content $configFile -Raw
    if ($raw -match "'DB_PASS'\s*=>\s*'([^']*)'") { $appPass = $matches[1] }
}
if (-not $appPass) {
    Write-Host "Could not read DB_PASS from config.php." -ForegroundColor Red
    exit 1
}
if ($appPass -eq 'CHANGE_ME') {
    Write-Host "DB_PASS in config.php is still CHANGE_ME. Set it to a strong password first." -ForegroundColor Red
    exit 1
}

# --- the MySQL root password ------------------------------------------------
# Asked for securely by default. The MYSQL_PWD environment variable is also
# honoured, which is handy for scripted setups.
if (-not $RootPassword) {
    if ($env:MYSQL_PWD) {
        $RootPassword = $env:MYSQL_PWD
    }
    else {
        $secure = Read-Host "MySQL root password for ${DbHost}:$Port" -AsSecureString
        $RootPassword = [System.Net.NetworkCredential]::new('', $secure).Password
    }
}
$env:MYSQL_PWD = $RootPassword

function Invoke-Sql {
    param([string]$Sql, [string]$Database = '')
    $args = @('-u', 'root', "--host=$DbHost", "--port=$Port", '--default-character-set=utf8mb4')
    if ($Database) { $args += $Database }
    $args += @('-e', $Sql)
    $output = & $MySqlExe @args 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host $output -ForegroundColor Red
        throw "MySQL command failed (exit $LASTEXITCODE)."
    }
    return $output
}

# Run a .sql file. A real file redirect is used rather than the client's
# `source` command, because `source` cannot handle a path with a space in it.
function Invoke-SqlFile {
    param([string]$File)
    $full = (Resolve-Path $File).Path
    $cmd  = '"' + $MySqlExe + '" -u root --host=' + $DbHost + ' --port=' + $Port +
            ' --default-character-set=utf8mb4 < "' + $full + '"'
    $output = & cmd.exe /c $cmd 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host $output -ForegroundColor Red
        throw "MySQL failed while running $File (exit $LASTEXITCODE)."
    }
    return $output
}

try {
    Write-Host "`n== 1/5  databases and tables ==" -ForegroundColor Cyan
    Invoke-SqlFile (Join-Path $PSScriptRoot 'schema.sql') | Out-Null
    Write-Host "   placement_tracker, placement_tracker_test, applications, users" -ForegroundColor Green

    Write-Host "`n== 2/5  the least-privilege application account ==" -ForegroundColor Cyan
    $escapedPass = $appPass.Replace("'", "''")
    Invoke-Sql -Sql @"
CREATE USER IF NOT EXISTS 'tracker_app'@'localhost' IDENTIFIED BY '$escapedPass';
ALTER USER 'tracker_app'@'localhost' IDENTIFIED BY '$escapedPass';
GRANT SELECT, INSERT, UPDATE, DELETE ON placement_tracker.*      TO 'tracker_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON placement_tracker_test.* TO 'tracker_app'@'localhost';
FLUSH PRIVILEGES;
"@ | Out-Null
    Write-Host "   tracker_app (SELECT, INSERT, UPDATE, DELETE only)" -ForegroundColor Green

    Write-Host "`n== 3/5  bringing an existing database up to date ==" -ForegroundColor Cyan
    if ($phpExe) {
        # The migration changes the schema, so it runs as the root user.
        $env:DB_USER = 'root'
        $env:DB_PASS = $RootPassword
        try {
            $migrate = & $phpExe (Join-Path $PSScriptRoot 'migrate.php') '--all' 2>&1
            $migrate | ForEach-Object { Write-Host "   $_" }
        }
        finally {
            $env:DB_USER = $null
            $env:DB_PASS = $null
        }
    }
    else {
        Write-Host "   PHP was not found, so db\migrate.php was skipped." -ForegroundColor Yellow
    }

    Write-Host "`n== 4/5  the demonstration rows ==" -ForegroundColor Cyan
    Invoke-SqlFile (Join-Path $PSScriptRoot 'seed.sql') | Out-Null
    Write-Host "   seeded" -ForegroundColor Green

    Write-Host "`n== 5/5  the first application account ==" -ForegroundColor Cyan
    if (-not $UserEmail) {
        $UserEmail    = Read-Host "Email address"
        $UserName     = Read-Host "Full name"
        $UserUsername = Read-Host "Username"
        $UserPassword = Read-Host "Password (at least 8 characters, a letter and a number)" -AsSecureString
        $UserPassword = [System.Net.NetworkCredential]::new('', $UserPassword).Password
    }
    if ($UserEmail -and $UserPassword) {
        if (-not $phpExe) {
            Write-Host "   PHP was not found, so the account was not created." -ForegroundColor Yellow
            Write-Host "   Run this later: php db\create_user.php" -ForegroundColor Yellow
        }
        else {
            $args = @(
                (Join-Path $PSScriptRoot 'create_user.php'),
                "--name=$UserName", "--email=$UserEmail",
                "--username=$UserUsername", "--password=$UserPassword"
            )
            $created = & $phpExe @args 2>&1
            $created | ForEach-Object { Write-Host "   $_" }
        }
    }
    else {
        Write-Host "   skipped - create the account later with: php db\create_user.php" -ForegroundColor Yellow
    }
}
finally {
    $env:MYSQL_PWD = $null
    $env:DB_PORT  = $null
    $env:DB_NAME  = $null
    $RootPassword = $null
}

Write-Host "`nSetup complete. Start the site with:" -ForegroundColor Cyan
Write-Host "   php -S localhost:8000" -ForegroundColor White
Write-Host "then open http://localhost:8000/`n" -ForegroundColor White
