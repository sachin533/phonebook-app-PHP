<#
    e2e.ps1 - the whole user journey, checked end to end.

    It signs in, walks every page, searches, filters, pages through the
    list and creates, changes and deletes a record.

        powershell -File tests\e2e.ps1
        powershell -File tests\e2e.ps1 -Base http://127.0.0.1:8099 -Email ada@example.com -Password S3cret-9
#>

param(
    [string]$Base     = 'http://127.0.0.1:8000',
    [string]$Email    = 'shubham@example.com',
    [string]$Password = 'Placement2026'
)

$ErrorActionPreference = 'Continue'
$script:pass = 0
$script:fail = 0
$script:jar  = Join-Path $env:TEMP ("pt_e2e_" + [guid]::NewGuid().ToString('N') + ".txt")

function Get-Page {
    param([string]$Path, [string]$Method = 'GET', [hashtable]$Body = $null)
    $args = @('-s', '-o', "$env:TEMP\pt_e2e_body.txt", '-w', '%{http_code}',
              '-b', $script:jar, '-c', $script:jar, '-X', $Method, "$Base$Path")
    if ($Body) {
        $args += @('--data-urlencode', "csrf=$($Body.csrf)")
        foreach ($key in $Body.Keys) {
            if ($key -ne 'csrf') { $args += @('--data-urlencode', "$key=$($Body[$key])") }
        }
    }
    $code = & curl.exe @args
    return @{
        Status = [int]$code
        Body   = (Get-Content "$env:TEMP\pt_e2e_body.txt" -Raw -ErrorAction SilentlyContinue)
    }
}

function Get-Headers {
    param([string]$Path, [string]$Method = 'GET', [hashtable]$Body = $null)
    $args = @('-s', '-D', '-', '-o', 'NUL', '-b', $script:jar, '-c', $script:jar, '-X', $Method, "$Base$Path")
    if ($Body) {
        $args += @('--data-urlencode', "csrf=$($Body.csrf)")
        foreach ($key in $Body.Keys) {
            if ($key -ne 'csrf') { $args += @('--data-urlencode', "$key=$($Body[$key])") }
        }
    }
    return (& curl.exe @args) -join "`n"
}

function Get-Token {
    param([string]$Path)
    $page = Get-Page $Path
    if ($page.Body -match 'name="csrf" value="([a-f0-9]{64})"') { return $matches[1] }
    return ''
}

function Check {
    param([string]$Name, [bool]$Condition, [string]$Detail = '')
    if ($Condition) {
        $script:pass++
        Write-Host ("  PASS  {0}" -f $Name) -ForegroundColor Green
    } else {
        $script:fail++
        Write-Host ("  FAIL  {0}  {1}" -f $Name, $Detail) -ForegroundColor Red
    }
}

Write-Host "`n== 1. a guest cannot see the pages ==" -ForegroundColor Cyan
foreach ($path in @('/dashboard.php', '/applications.php', '/companies.php', '/statistics.php', '/profile.php', '/new.php')) {
    $page = Get-Page $path
    Check "$path redirects a guest" ($page.Status -eq 302) "(got $($page.Status))"
}
$page = Get-Page '/login.php'
Check "/login.php is reachable" ($page.Status -eq 200) "(got $($page.Status))"
Check "the login form has a password field" ($page.Body -match 'type="password"')
Check "the login form has no fake Google button" (-not ($page.Body -match 'Google'))

Write-Host "`n== 2. signing in ==" -ForegroundColor Cyan
$token = Get-Token '/login.php'
Check "the login form carries a CSRF token" ($token -ne '')

$page = Get-Page '/login.php' 'POST' @{ csrf = $token; identifier = $Email; password = 'wrong-password' }
Check "a wrong password is rejected with 422" ($page.Status -eq 422) "(got $($page.Status))"
Check "the error message is shown" ($page.Body -match 'not right')

$token = Get-Token '/login.php'
$headers = Get-Headers '/login.php' 'POST' @{ csrf = $token; identifier = $Email; password = $Password }
Check "the right password signs in with a 302" ($headers -match 'HTTP/1.1 302') ''
Check "the sign in redirects to the dashboard" ($headers -match 'Location: /dashboard\.php')

Write-Host "`n== 3. the dashboard ==" -ForegroundColor Cyan
$page = Get-Page '/dashboard.php'
Check "the dashboard loads" ($page.Status -eq 200) "(got $($page.Status))"
Check "the sidebar is there" ($page.Body -match 'class="sidebar"')
Check "the total is shown" ($page.Body -match 'Total Applications')
Check "the status donut is drawn" ($page.Body -match 'class="donut"')
Check "the monthly chart is drawn" ($page.Body -match 'class="bar-chart"')
Check "the top bar search is there" ($page.Body -match 'data-search-input')

Write-Host "`n== 4. the applications list ==" -ForegroundColor Cyan
$page = Get-Page '/applications.php'
Check "the list loads" ($page.Status -eq 200) "(got $($page.Status))"
Check "it shows the row count" ($page.Body -match 'of <strong>\d+</strong> results')
Check "there are only ten rows on a page" (([regex]::Matches($page.Body, 'class="company-name"')).Count -eq 10) ''
Check "the pagination is there" ($page.Body -match 'class="pagination"')
Check "the page size selector is there" ($page.Body -match 'data-per-page')

Write-Host "`n== 5. search and filters ==" -ForegroundColor Cyan
# A record of its own, so the test does not depend on what is already in
# the database.
$probe = 'Search Probe ' + (Get-Random -Maximum 999999)
$token = Get-Token '/new.php'
$headers = Get-Headers '/new.php' 'POST' @{
    csrf = $token; company = $probe; role = 'Probe Role'; status = 'Applied'
    applied_on = '2026-09-16'; location = 'Probeland, Testville'; salary = '9 LPA'
    job_url = 'https://careers.example.com/probe'; notes = 'A record the search test looks for.'
}
Check "the probe record was saved" ($headers -match 'HTTP/1.1 302') ''
$probeId = 0
if ($headers -match 'Location: /view\.php\?id=(\d+)') { $probeId = [int]$matches[1] }

$page = Get-Page "/applications.php?q=$([uri]::EscapeDataString($probe))"
Check "a search by company works" ($page.Status -eq 200)
Check "the search finds the record" ($page.Body -match [regex]::Escape($probe)) ''
$page2 = Get-Page ("/applications.php?q=" + [uri]::EscapeDataString($probe.ToLower()))
Check "the search is case insensitive" ($page2.Body -match [regex]::Escape($probe)) ''

$page = Get-Page "/applications.php?q=$([uri]::EscapeDataString('Probe Role'))"
Check "a search by role works" ($page.Body -match 'Probe Role')
$page = Get-Page "/applications.php?q=$([uri]::EscapeDataString('Probeland'))"
Check "a search by location works" ($page.Body -match 'Probeland')
$page = Get-Page "/applications.php?q=$([uri]::EscapeDataString('Applied'))"
Check "a search by status works" ($page.Body -match 'Applied')
$page = Get-Page '/applications.php?q=zzzznothingmatches'
Check "a search with no results says so" ($page.Body -match 'No matching applications')

$page = Get-Page '/applications.php?status=Offer'
Check "the status filter works" ($page.Body -match 'badge-offer')
$page = Get-Page "/applications.php?q=$([uri]::EscapeDataString($probe))&status=Applied"
Check "search and filter combine" ($page.Status -eq 200)

Write-Host "`n== 6. pagination ==" -ForegroundColor Cyan
$page = Get-Page '/applications.php?page=1'
Check "the first page works" ($page.Status -eq 200)
$page = Get-Page '/applications.php?page=99999'
Check "a page number past the end is clamped" ($page.Status -eq 200)
$page = Get-Page "/applications.php?page=2&q=$([uri]::EscapeDataString($probe))"
Check "a search and a page number work together" ($page.Status -eq 200)
$page = Get-Page '/applications.php?per_page=20'
$rows = ([regex]::Matches($page.Body, 'class="company-name"')).Count
Check "the page size can be changed to 20" ($rows -eq 20) "(got $rows rows)"

Write-Host "`n== 7. the JSON search endpoint ==" -ForegroundColor Cyan
$json = & curl.exe -s -b $script:jar -c $script:jar "$Base/api/applications.php?q=$([uri]::EscapeDataString($probe))"
$data = $json | ConvertFrom-Json
Check "the endpoint answers JSON" ($data.ok -eq $true)
Check "it reports the total" ($data.total -gt 0) "(total $($data.total))"
Check "it returns the table" ($data.table_html -match 'company-name')
Check "it returns the pagination" ($data.pagination_html -match 'pagination-bar')

Write-Host "`n== 8. the other pages ==" -ForegroundColor Cyan
foreach ($path in @('/companies.php', '/statistics.php', '/profile.php', '/new.php')) {
    $page = Get-Page $path
    Check "$path loads" ($page.Status -eq 200) "(got $($page.Status))"
}

Write-Host "`n== 9. create, read, update, delete ==" -ForegroundColor Cyan
$token = Get-Token '/new.php'
$headers = Get-Headers '/new.php' 'POST' @{
    csrf = $token; company = 'E2E Test Co'; role = 'PHP Developer'; status = 'Applied'
    applied_on = '2026-09-16'; location = 'Bengaluru, Karnataka'; salary = '12 LPA'
    job_url = 'https://careers.example.com/e2e'; notes = 'Created by the end to end test.'
}
Check "the new application is saved" ($headers -match 'HTTP/1.1 302') ''
$id = 0
if ($headers -match 'Location: /view\.php\?id=(\d+)') { $id = [int]$matches[1] }
Check "the redirect points at the new record" ($id -gt 0) "(id $id)"

$page = Get-Page "/view.php?id=$id"
Check "the record can be read" ($page.Status -eq 200)
Check "it shows the company" ($page.Body -match 'E2E Test Co')
Check "it shows the location" ($page.Body -match 'Bengaluru, Karnataka')
Check "it shows the salary" ($page.Body -match '12 LPA')

$token = Get-Token "/edit.php?id=$id"
$headers = Get-Headers "/edit.php?id=$id" 'POST' @{
    csrf = $token; company = 'E2E Test Co'; role = 'PHP Developer'; status = 'Interview'
    applied_on = '2026-09-16'; location = 'Bengaluru, Karnataka'; salary = '12 LPA'
    job_url = 'https://careers.example.com/e2e'; notes = 'Status changed.'
}
Check "the change is saved" ($headers -match 'HTTP/1.1 302') ''
$page = Get-Page "/view.php?id=$id"
Check "the new status is stored" ($page.Body -match 'Interview')

$token = Get-Token "/view.php?id=$id"
$headers = Get-Headers '/delete.php' 'POST' @{ csrf = $token; id = "$id" }
Check "the record is deleted" ($headers -match 'HTTP/1.1 302') ''
$page = Get-Page "/view.php?id=$id"
Check "the record is gone" ($page.Status -eq 404) "(got $($page.Status))"

Write-Host "`n== 10. signing out ==" -ForegroundColor Cyan
# The probe record is not needed any more.
if ($probeId -gt 0) {
    $token = Get-Token "/view.php?id=$probeId"
    $headers = Get-Headers '/delete.php' 'POST' @{ csrf = $token; id = "$probeId" }
    Check "the probe record is cleaned up" ($headers -match 'HTTP/1.1 302') ''
}

$token = Get-Token '/dashboard.php'
$headers = Get-Headers '/logout.php' 'POST' @{ csrf = $token }
Check "signing out redirects" ($headers -match 'HTTP/1.1 302') ''
$page = Get-Page '/dashboard.php'
Check "the pages are protected again" ($page.Status -eq 302) "(got $($page.Status))"

Remove-Item $script:jar -Force -ErrorAction SilentlyContinue
Remove-Item "$env:TEMP\pt_e2e_body.txt" -Force -ErrorAction SilentlyContinue

Write-Host ""
Write-Host ("Passed: {0}   Failed: {1}" -f $script:pass, $script:fail) -ForegroundColor $(if ($script:fail -eq 0) { 'Green' } else { 'Red' })
exit $script:fail
