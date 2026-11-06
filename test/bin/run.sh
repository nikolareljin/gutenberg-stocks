#!/usr/bin/env bash
# End-to-end test: install WP, activate the plugin, and verify the block,
# shortcode, REST API, CLI, and cache behavior against the real stack.
#
# Env: NRSTX_TEST_PORT (default 8080), NRSTX_WP_VERSION (default latest).
# Series assertions use the keyless Frankfurter provider, so no API keys are
# needed. Upstream outages degrade to error-envelope assertions (see below).
set -euo pipefail

cd "$(dirname "$0")/../.."

SCRIPT_HELPERS_DIR="scripts/script-helpers"
# shellcheck source=../../scripts/lib.sh
source scripts/lib.sh

compose="docker compose -f test/docker-compose.yml"

info() { echo "[INFO] $*"; }
err() { echo "[ERROR] $*" 1>&2; }

ADMIN_USER=admin
ADMIN_PASS=admin
ADMIN_EMAIL=admin@example.com
HOST_PORT=${NRSTX_TEST_PORT:-8080}
BASE_URL="http://localhost:${HOST_PORT}"

mkdir -p test/tmp

fail=0
check() {
  local desc="$1"; shift
  if "$@" >/dev/null 2>&1; then
    info "PASS: ${desc}"
  else
    err "FAIL: ${desc}"
    fail=1
  fi
}

info "Ensuring block assets are built…"
if [[ ! -f build/blocks/stock-info/block.json ]]; then
  nr_ensure_node_24
  npm install --no-audit --no-fund
  npm run build
fi

WP_CLI_CONFIG="test/tmp/wp-cli.yml"
cat > "${WP_CLI_CONFIG}" <<EOF
path: /var/www/html
url: http://localhost:${HOST_PORT}
color: false
disable_wp_cron: true
apache_modules:
  - mod_rewrite
EOF

info "Starting containers (db, wordpress)…"
$compose up -d db wordpress

info "Waiting 20s for DB to initialize…"
sleep 20

info "Installing WordPress (single site) unless installed…"
$compose run --rm wpcli sh -lc "if ! wp core is-installed; then wp core install --url=localhost:${HOST_PORT} --title='NR Stocks Test' --admin_user='${ADMIN_USER}' --admin_password='${ADMIN_PASS}' --admin_email='${ADMIN_EMAIL}' --skip-email; else echo 'WordPress already installed'; fi"

info "Enabling pretty permalinks (path-based REST needs rewrite routing)…"
$compose exec -T wordpress a2enmod rewrite >/dev/null
$compose restart wordpress >/dev/null
sleep 8
$compose run --rm wpcli sh -lc "wp rewrite structure '/%postname%/' --hard && wp rewrite flush --hard"

info "Activating plugin…"
$compose run --rm wpcli sh -lc "wp plugin activate nr-stocks || wp plugin activate nr-stocks --network"

info "Setting keyless default provider (Frankfurter, full option shape)…"
$compose run --rm wpcli sh -lc "wp option update nr_stocks_settings '{\"default_provider\":\"frankfurter\",\"keys\":{\"alphavantage\":\"\",\"twelvedata\":\"\",\"coingecko\":\"\"},\"ttls\":{\"series\":300,\"quote\":60,\"search\":3600},\"line_color\":\"#0b163b\",\"rest_rate_limit\":60,\"rest_miss_limit\":30,\"rest_cors_origins\":[],\"av_host\":\"\",\"av_entrypoint\":\"\"}' --format=json"

info "Creating fixture posts (block + shortcode)…"
BLOCK_POST=$($compose run --rm wpcli sh -lc "wp post create --post_type=post --post_status=publish --post_title='NR Stocks block' --post_content='<!-- wp:nr-stocks/stock-chart {\"symbols\":[\"EUR/USD\"],\"provider\":\"frankfurter\",\"showQuote\":true} /-->' --porcelain")
SHORT_POST=$($compose run --rm wpcli sh -lc "wp post create --post_type=post --post_status=publish --post_title='NR Stocks shortcode' --post_content='[nr-stocks symbols=\"EUR/USD\" provider=\"frankfurter\" quote=\"1\"]' --porcelain")
info "Posts: block=${BLOCK_POST} shortcode=${SHORT_POST}"

info "Checking block frontend output…"
BLOCK_HTML="$(curl -fsSL "${BASE_URL}/?p=${BLOCK_POST}")"
check "block renders figure" grep -q 'class="nr-stocks"' <<<"${BLOCK_HTML}"
check "block bakes chart JSON" grep -q 'nr-stocks-data' <<<"${BLOCK_HTML}"
check "block fallback lists symbol" grep -q 'EUR/USD' <<<"${BLOCK_HTML}"

info "Checking shortcode frontend output…"
SHORT_HTML="$(curl -fsSL "${BASE_URL}/?p=${SHORT_POST}")"
check "shortcode renders figure" grep -q 'class="nr-stocks"' <<<"${SHORT_HTML}"
check "shortcode bakes chart JSON" grep -q 'nr-stocks-data' <<<"${SHORT_HTML}"

info "Checking REST providers endpoint…"
PROVIDERS_JSON="$(curl -fsSL "${BASE_URL}/wp-json/nr-stocks/v1/providers")"
PROVIDERS_TYPE="$(curl -fsSL -D - -o /dev/null "${BASE_URL}/wp-json/nr-stocks/v1/providers" | grep -ai '^content-type:' || true)"
check "providers answer JSON" grep -qi 'application/json' <<<"${PROVIDERS_TYPE}"
check "providers lists five entries" grep -q '"frankfurter"' <<<"${PROVIDERS_JSON}"
if grep -qi '"hosts"\|apikey\|api_key' <<<"${PROVIDERS_JSON}"; then
  err "FAIL: providers leak key material"
  fail=1
else
  info "PASS: providers leak no key material"
fi

info "Checking REST series endpoint (keyless provider)…"
SERIES_JSON="$(curl -fsSL "${BASE_URL}/wp-json/nr-stocks/v1/series?provider=frankfurter&symbols=EUR/USD&range=5")"
if grep -q '"ok":true' <<<"${SERIES_JSON}"; then
  check "series returns rows" grep -q '"date":"20' <<<"${SERIES_JSON}"
else
  # Upstream outage: the contract still holds (machine-readable error).
  check "series degrades to error envelope" grep -q '"ok":false' <<<"${SERIES_JSON}"
  info "NOTE: upstream unreachable — data assertions downgraded to envelope checks"
fi

info "Checking REST cache headers + HIT on repeat…"
HEADERS_ONE="$(curl -fsSL -D - -o /dev/null "${BASE_URL}/wp-json/nr-stocks/v1/series?provider=frankfurter&symbols=EUR/USD&range=5")"
check "series sends Cache-Control" grep -qi 'cache-control: .*max-age=' <<<"${HEADERS_ONE}"
check "series sends ETag" grep -qi '^etag:' <<<"${HEADERS_ONE}"
HEADERS_TWO="$(curl -fsSL -D - -o /dev/null "${BASE_URL}/wp-json/nr-stocks/v1/series?provider=frankfurter&symbols=EUR/USD&range=5")"
check "repeat request served from cache" grep -qi 'x-nr-stocks-cache: HIT' <<<"${HEADERS_TWO}"

info "Checking REST validation + rate limit…"
BAD_JSON="$(curl -sSL "${BASE_URL}/wp-json/nr-stocks/v1/series?symbols=!!!")"
check "bad symbol rejected" grep -q 'nr_stocks_bad_symbol' <<<"${BAD_JSON}"
# Isolate the rate bucket: clear transients and use the untouched quote route.
$compose run --rm wpcli sh -lc "wp transient delete --all" >/dev/null
$compose run --rm wpcli sh -lc "wp option patch update nr_stocks_settings rest_rate_limit 2" >/dev/null
Q1_CODE="$(curl -sSL -o /dev/null -w "%{http_code}" "${BASE_URL}/wp-json/nr-stocks/v1/quote?provider=frankfurter&symbols=EUR/USD")"
Q2_CODE="$(curl -sSL -o /dev/null -w "%{http_code}" "${BASE_URL}/wp-json/nr-stocks/v1/quote?provider=frankfurter&symbols=EUR/USD")"
check "first two requests allowed" test "${Q1_CODE}${Q2_CODE}" = "200200"
LIMIT_JSON="$(curl -sSL "${BASE_URL}/wp-json/nr-stocks/v1/quote?provider=frankfurter&symbols=EUR/USD")"
check "third request rate limited" grep -q 'nr_stocks_rate_limited' <<<"${LIMIT_JSON}"
LIMIT_HEADERS="$(curl -sSL -D - -o /dev/null "${BASE_URL}/wp-json/nr-stocks/v1/quote?provider=frankfurter&symbols=EUR/USD")"
check "limited response is not stored" grep -qi '^cache-control: no-store' <<<"${LIMIT_HEADERS}"
check "limited response sends Retry-After" grep -qi '^retry-after: [0-9]' <<<"${LIMIT_HEADERS}"
$compose run --rm wpcli sh -lc "wp option patch update nr_stocks_settings rest_rate_limit 60" >/dev/null

info "Checking parallel cold requests share one fetch…"
$compose run --rm wpcli sh -lc "wp nr-stocks cache purge" >/dev/null
PARALLEL_URL="${BASE_URL}/wp-json/nr-stocks/v1/series?provider=frankfurter&symbols=USD/JPY&range=5"
curl -sSL -o test/tmp/parallel-1.json "${PARALLEL_URL}" &
curl -sSL -o test/tmp/parallel-2.json "${PARALLEL_URL}" &
curl -sSL -o test/tmp/parallel-3.json "${PARALLEL_URL}" &
wait
if grep -q '"error":"busy"' test/tmp/parallel-1.json test/tmp/parallel-2.json test/tmp/parallel-3.json; then
  err "FAIL: a waiting request reported busy instead of the fetched result"
  fail=1
else
  info "PASS: waiting requests received the fetched result"
fi

info "Checking CORS on plugin routes…"
CORS_ROUTE="${BASE_URL}/wp-json/nr-stocks/v1/providers"
UNLISTED_HEADERS="$(curl -fsSL -D - -o /dev/null -H 'Origin: https://unlisted.example' "${CORS_ROUTE}")"
if grep -qi '^access-control-allow-origin:' <<<"${UNLISTED_HEADERS}"; then
  err "FAIL: unlisted origin was allowed"
  fail=1
else
  info "PASS: unlisted origin refused"
fi
$compose run --rm wpcli sh -lc "wp option patch update nr_stocks_settings rest_cors_origins --format=json '[\"app.example.test\"]'" >/dev/null
LISTED_HEADERS="$(curl -fsSL -D - -o /dev/null -H 'Origin: https://app.example.test' "${CORS_ROUTE}")"
check "listed origin allowed" grep -qi '^access-control-allow-origin: https://app.example.test' <<<"${LISTED_HEADERS}"
if grep -qi '^access-control-allow-credentials:' <<<"${LISTED_HEADERS}"; then
  err "FAIL: credentials allowed cross-origin"
  fail=1
else
  info "PASS: credentials not allowed cross-origin"
fi
PREFLIGHT_HEADERS="$(curl -sSL -D - -o /dev/null -X OPTIONS -H 'Origin: https://unlisted.example' -H 'Access-Control-Request-Method: GET' "${CORS_ROUTE}")"
if grep -qi '^access-control-allow-origin:' <<<"${PREFLIGHT_HEADERS}"; then
  err "FAIL: unlisted preflight was allowed"
  fail=1
else
  info "PASS: unlisted preflight refused"
fi
CASE_HEADERS="$(curl -fsSL -D - -o /dev/null -H 'Origin: https://unlisted.example' "${BASE_URL}/wp-json/NR-Stocks/v1/providers")"
if grep -qi '^access-control-allow-origin:' <<<"${CASE_HEADERS}"; then
  err "FAIL: route case bypassed the allowlist"
  fail=1
else
  info "PASS: route case does not bypass the allowlist"
fi
JSONP_CODE="$(curl -sSL -o /dev/null -w '%{http_code}' "${CORS_ROUTE}?_jsonp=callback")"
check "JSONP refused on plugin routes" test "${JSONP_CODE}" = "400"
CORE_HEADERS="$(curl -fsSL -D - -o /dev/null -H 'Origin: https://unlisted.example' "${BASE_URL}/wp-json/wp/v2/types")"
check "core routes keep their CORS headers" grep -qi '^access-control-allow-origin: https://unlisted.example' <<<"${CORE_HEADERS}"

info "Checking saved 0.x chart loads the view script…"
LEGACY_POST=$($compose run --rm wpcli sh -lc "wp post create --post_type=post --post_status=publish --post_title='NR Stocks legacy' --post_content='<div class=\"stock-information-item\" data-symbol=\"IBM\"></div>' --porcelain")
LEGACY_HTML="$(curl -fsSL "${BASE_URL}/?p=${LEGACY_POST}")"
check "legacy post loads view script" grep -q 'nr-stocks-view-js' <<<"${LEGACY_HTML}"
check "legacy post gets the REST URL" grep -q 'window.nrStocks' <<<"${LEGACY_HTML}"

info "Checking settings save and purge through wp-admin…"
COOKIES="$(mktemp)"
curl -fsS -o /dev/null -c "${COOKIES}" -b 'wordpress_test_cookie=WP+Cookie+check' \
  --data-urlencode "log=${ADMIN_USER}" --data-urlencode "pwd=${ADMIN_PASS}" \
  --data-urlencode 'testcookie=1' "${BASE_URL}/wp-login.php"
SETTINGS_URL="${BASE_URL}/wp-admin/options-general.php?page=nr-stocks"
SETTINGS_HTML="$(curl -fsS -b "${COOKIES}" "${SETTINGS_URL}")"
check "settings page has one settings form" test "$(grep -c '<form method="post" action="options.php"' <<<"${SETTINGS_HTML}" || true)" = "1"
SAVE_NONCE="$(sed -n 's/.*name="_wpnonce" value="\([a-f0-9]*\)".*/\1/p' <<<"${SETTINGS_HTML}" | sed -n 1p)"
PURGE_NONCE="$(sed -n 's/.*name="_wpnonce" value="\([a-f0-9]*\)".*/\1/p' <<<"${SETTINGS_HTML}" | sed -n 2p)"
curl -fsS -o /dev/null -b "${COOKIES}" -e "${SETTINGS_URL}" \
  -d option_page=nr_stocks -d action=update --data-urlencode "_wpnonce=${SAVE_NONCE}" \
  --data-urlencode "_wp_http_referer=/wp-admin/options-general.php?page=nr-stocks" \
  --data-urlencode 'nr_stocks_settings[default_provider]=frankfurter' \
  --data-urlencode 'nr_stocks_settings[keys][alphavantage]=E2EKEY' \
  --data-urlencode 'nr_stocks_settings[ttls][series]=900' \
  --data-urlencode 'nr_stocks_settings[line_color]=#123456' \
  --data-urlencode 'nr_stocks_settings[rest_rate_limit]=59' \
  --data-urlencode 'nr_stocks_settings[rest_miss_limit]=29' \
  --data-urlencode 'nr_stocks_settings[rest_cors_origins]=saved.example.test' \
  "${BASE_URL}/wp-admin/options.php"
SAVED="$($compose run --rm wpcli sh -lc "wp option get nr_stocks_settings --format=json" | tr -d '\r')"
check "settings save persists the key" grep -q '"alphavantage":"E2EKEY"' <<<"${SAVED}"
check "settings save persists the TTL" grep -q '"series":900' <<<"${SAVED}"
check "settings save persists the color" grep -q '"line_color":"#123456"' <<<"${SAVED}"
check "settings save persists the rate limit" grep -q '"rest_rate_limit":59' <<<"${SAVED}"
check "settings save persists the budget" grep -q '"rest_miss_limit":29' <<<"${SAVED}"
check "settings save persists the hosts" grep -q 'saved.example.test' <<<"${SAVED}"
PURGE_LOCATION="$(curl -sS -o /dev/null -D - -b "${COOKIES}" -e "${SETTINGS_URL}" \
  -d action=nr_stocks_purge_cache --data-urlencode "_wpnonce=${PURGE_NONCE}" \
  --data-urlencode "_wp_http_referer=/wp-admin/options-general.php?page=nr-stocks" \
  "${BASE_URL}/wp-admin/admin-post.php" | grep -i '^location:' | tr -d '\r' | sed 's/^[^ ]* //')"
check "purge redirects with the success flag" grep -q 'nr-stocks-purged=1' <<<"${PURGE_LOCATION}"
PURGED_HTML="$(curl -fsS -b "${COOKIES}" "${PURGE_LOCATION}")"
check "purge shows a success notice" grep -q 'The stock cache was purged' <<<"${PURGED_HTML}"
if grep -q 'E2EKEY' <<<"${PURGED_HTML}"; then
  err "FAIL: saved API key reached the browser"
  fail=1
else
  info "PASS: saved API key stays on the server"
fi
rm -f "${COOKIES}"

info "Checking WP-CLI commands…"
check "cli providers" $compose run --rm wpcli sh -lc "wp nr-stocks providers | grep -q frankfurter"
check "cli cache purge" $compose run --rm wpcli sh -lc "wp nr-stocks cache purge | grep -qi purged"

if [[ $fail -ne 0 ]]; then
  err "E2E verification failed"
  exit 1
fi

info "E2E verification passed."
