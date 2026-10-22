#!/usr/bin/env bash
# Fallback without PHPUnit: ./tests/smoke.sh http://127.0.0.1:8000
#
# It signs in and checks the status code of every route. Use tests\smoke.ps1
# on Windows, or this file in Git Bash / WSL.

base="${1:-http://127.0.0.1:8000}"
email="${2:-shubham@example.com}"
password="${3:-Placement2026}"
jar="$(mktemp)"
fail=0

check() {
  got=$(curl -s -o /dev/null -w '%{http_code}' -b "$jar" -c "$jar" -X "$1" "$base$2")
  if [ "$got" = "$3" ]; then r=PASS; else r=FAIL; fail=1; fi
  printf '%-4s %-34s expected %s got %s %s\n' "$1" "$2" "$3" "$got" "$r"
}

csrf() {
  curl -s -b "$jar" -c "$jar" "$base$1" \
    | grep -o 'name="csrf" value="[a-f0-9]*"' \
    | head -1 | sed 's/.*value="//; s/"//'
}

echo "== a guest is sent to the login page =="
check GET  /dashboard.php    302
check GET  /applications.php 302
check GET  /companies.php    302
check GET  /statistics.php   302
check GET  /profile.php      302
check GET  /login.php        200
check GET  /register.php     200

echo ""
echo "== signing in =="
token=$(csrf /login.php)
got=$(curl -s -o /dev/null -w '%{http_code}' -b "$jar" -c "$jar" -X POST "$base/login.php" \
  --data-urlencode "csrf=$token" \
  --data-urlencode "identifier=$email" \
  --data-urlencode "password=$password")
if [ "$got" = "302" ]; then r=PASS; else r=FAIL; fail=1; fi
printf '%-4s %-34s expected %s got %s %s\n' POST /login.php 302 "$got" "$r"

echo ""
echo "== the signed in pages =="
check GET  /dashboard.php       200
check GET  /applications.php    200
check GET  /companies.php       200
check GET  /statistics.php      200
check GET  /profile.php         200
check GET  /new.php             200
check GET  /index.php           302
check GET  "/view.php?id=999999" 404
check GET  "/view.php?id=abc"   404
check GET  "/edit.php?id=999999" 404
check GET  /delete.php          405
check POST /delete.php          403

echo ""
echo "== the search and the pagination =="
check GET "/applications.php?q=google"        200
check GET "/applications.php?page=2&q=google" 200
check GET "/applications.php?status=Offer"    200
check GET "/applications.php?per_page=20"     200
check GET "/api/applications.php?q=google"     200

rm -f "$jar"
exit $fail
