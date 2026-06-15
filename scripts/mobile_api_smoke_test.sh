#!/usr/bin/env bash
# Smoke-test production mobile API. Optional credentials:
#   MOBILE_TEST_EMAIL=user@example.com MOBILE_TEST_PASSWORD=secret ./scripts/mobile_api_smoke_test.sh
set -euo pipefail

BASE="${MOBILE_API_BASE:-https://portal.bit-sole.com/api}"
PASS=0
FAIL=0

check() {
  local name="$1"
  local expected="$2"
  local actual="$3"
  if [[ "$actual" == "$expected" ]]; then
    echo "✓ $name (HTTP $actual)"
    PASS=$((PASS + 1))
  else
    echo "✗ $name (expected $expected, got $actual)"
    FAIL=$((FAIL + 1))
  fi
}

http_code() {
  curl -s -o /dev/null -w "%{http_code}" "$@"
}

echo "=== BITSole Mobile API smoke test ==="
echo "Base: $BASE"
echo

check "Login validation (missing body)" 422 "$(http_code -X POST "$BASE/auth/login" -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{}')"
check "Register validation (missing body)" 422 "$(http_code -X POST "$BASE/auth/register" -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{}')"
check "Me without token" 401 "$(http_code "$BASE/auth/me" -H 'Accept: application/json')"
check "Live positions without token" 401 "$(http_code "$BASE/live/positions/current" -H 'Accept: application/json')"
check "Vehicles without token" 401 "$(http_code "$BASE/vehicles" -H 'Accept: application/json')"
check "Drivers without token" 401 "$(http_code "$BASE/drivers" -H 'Accept: application/json')"
check "Maintenance without token" 401 "$(http_code "$BASE/vehicles/maintenance" -H 'Accept: application/json')"
check "Notifications without token" 401 "$(http_code "$BASE/notifications/events" -H 'Accept: application/json')"
check "Acknowledge without token" 401 "$(http_code -X POST "$BASE/monitoring/vehicles/events/1/acknowledge" -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"remarks":"test"}')"

if [[ -n "${MOBILE_TEST_EMAIL:-}" && -n "${MOBILE_TEST_PASSWORD:-}" ]]; then
  echo
  echo "=== Authenticated flow ==="
  LOGIN_RESP=$(curl -s -X POST "$BASE/auth/login" \
    -H 'Accept: application/json' \
    -H 'Content-Type: application/json' \
    -d "{\"email\":\"$MOBILE_TEST_EMAIL\",\"password\":\"$MOBILE_TEST_PASSWORD\"}")
  TOKEN=$(php -r '$j=json_decode(file_get_contents("php://stdin"), true); echo $j["token"] ?? "";' <<<"$LOGIN_RESP")

  if [[ -z "$TOKEN" ]]; then
    echo "✗ Login failed — no token returned"
    echo "$LOGIN_RESP"
    FAIL=$((FAIL + 1))
  else
    echo "✓ Login returned token"
    PASS=$((PASS + 1))
    AUTH=(-H "Authorization: Bearer $TOKEN" -H 'Accept: application/json')

    check "GET /auth/me" 200 "$(http_code "$BASE/auth/me" "${AUTH[@]}")"
    check "GET /live/positions/current" 200 "$(http_code "$BASE/live/positions/current" "${AUTH[@]}")"
    check "GET /vehicles" 200 "$(http_code "$BASE/vehicles" "${AUTH[@]}")"
    check "GET /drivers" 200 "$(http_code "$BASE/drivers" "${AUTH[@]}")"
    check "GET /notifications/events" 200 "$(http_code "$BASE/notifications/events" "${AUTH[@]}")"
    check "GET /notifications/unread-count" 200 "$(http_code "$BASE/notifications/unread-count" "${AUTH[@]}")"
    check "POST /notifications/mark-read" 200 "$(http_code -X POST "$BASE/notifications/mark-read" "${AUTH[@]}")"
    check "POST /auth/logout" 200 "$(http_code -X POST "$BASE/auth/logout" "${AUTH[@]}")"
  fi
else
  echo
  echo "Skipping authenticated tests (set MOBILE_TEST_EMAIL and MOBILE_TEST_PASSWORD to run full flow)."
fi

echo
echo "=== Summary: $PASS passed, $FAIL failed ==="
[[ "$FAIL" -eq 0 ]]
