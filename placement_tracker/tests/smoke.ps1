<#
    smoke.ps1 - the status code checks, with no PHPUnit needed.

    It signs in, walks the routes and checks the status code of each one.
    It is the fallback for a machine where PHPUnit is not installed.

        powershell -File tests\smoke.ps1
        powershell -File tests\smoke.ps1 -Base http://127.0.0.1:8099
#>

param(
    [string]$Base     = 'http://127.0.0.1:8000',
    [string]$Email    = 'shubham@example.com',
    [string]$Password = 'Placement2026'
)

$ErrorActionPreference = 'Continue'
$script:fail = 0
$jar = Join-Path $env:TEMP ("pt_smoke_" + [guid]::NewGuid().ToString('N') + ".txt")

function Invoke-Check {
    param(
        [string]$Method,
        [string]$Path,
        [int]$Expected,
        [string]$Note = ''
    )

    $args = @('-s', '-o', 'NUL', '-w', '%{http_code}', '-b', $jar, '-c', $jar, '-X', $Method, "$Base$Path")
    $got = [int](& curl.exe @args)

    if ($got -eq $Expected) {
        $result = 'PASS'
    } else {
        $result = 'FAIL'
        $script:fail = 1
    }

    '{0,-4} {1,-34} expected {2} got {3} {4} {5}' -f $Method, $Path, $Expected, $got, $result, $Note
}

function Get-Csrf {
    param([string]$Path)
    # curl writes one line at a time, so the output is joined back together.
    $body = (& curl.exe -s -b $jar -c $jar "$Base$Path") -join "`n"
    if ($body -match 'name="csrf" value="([a-f0-9]{64})"') { return $matches[1] }
    return ''
}

Write-Host "`n== a guest is sent to the login page ==" -ForegroundColor Cyan
Invoke-Check 'GET' '/dashboard.php'    302
Invoke-Check 'GET' '/applications.php' 302
Invoke-Check 'GET' '/companies.php'    302
Invoke-Check 'GET' '/statistics.php'   302
Invoke-Check 'GET' '/profile.php'      302
Invoke-Check 'GET' '/login.php'        200
Invoke-Check 'GET' '/register.php'     200

Write-Host "`n== signing in ==" -ForegroundColor Cyan
$token = Get-Csrf '/login.php'
$code = [int](& curl.exe -s -o NUL -w '%{http_code}' -b $jar -c $jar -X POST "$Base/login.php" `
    --data-urlencode "csrf=$token" --data-urlencode "identifier=$Email" --data-urlencode "password=$Password")
if ($code -eq 302) { Write-Host "  sign in with the right password                expected 302 got 302 PASS" }
else { Write-Host "  sign in with the right password                expected 302 got $code FAIL"; $script:fail = 1 }

Write-Host "`n== the signed in pages ==" -ForegroundColor Cyan
Invoke-Check 'GET' '/dashboard.php'     200
Invoke-Check 'GET' '/applications.php'  200
Invoke-Check 'GET' '/companies.php'     200
Invoke-Check 'GET' '/statistics.php'    200
Invoke-Check 'GET' '/profile.php'       200
Invoke-Check 'GET' '/new.php'           200
Invoke-Check 'GET' '/index.php'         302 'the old address redirects'
Invoke-Check 'GET' '/view.php?id=999999' 404
Invoke-Check 'GET' '/view.php?id=abc'   404
Invoke-Check 'GET' '/edit.php?id=999999' 404
Invoke-Check 'GET' '/delete.php'        405
Invoke-Check 'POST' '/delete.php'       403

Write-Host "`n== the search and the pagination ==" -ForegroundColor Cyan
Invoke-Check 'GET' '/applications.php?q=google'      200
Invoke-Check 'GET' '/applications.php?page=2&q=google' 200
Invoke-Check 'GET' '/applications.php?status=Offer'  200
Invoke-Check 'GET' '/applications.php?per_page=20'   200
Invoke-Check 'GET' '/api/applications.php?q=google'   200

Remove-Item $jar -Force -ErrorAction SilentlyContinue

Write-Host ""
if ($script:fail -eq 0) { Write-Host "Every check passed." -ForegroundColor Green }
else { Write-Host "Some checks failed." -ForegroundColor Red }
exit $script:fail
