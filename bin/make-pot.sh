#!/usr/bin/env bash
# Regenerate languages/nr-stocks.pot (PHP + JS strings) via WP-CLI in Docker.
# Newer wp-cli images no longer bundle `wp i18n`, so the i18n-command package
# install below is the live path on fresh images — with retries and optional
# token auth because anonymous GitHub API calls rate-limit (esp. on shared
# CI runners).
set -euo pipefail

cd "$(dirname "$0")/.."

compose="docker compose -f test/docker-compose.yml"
PLUGIN_DIR=/var/www/html/wp-content/plugins/nr-stocks
# Same install identity as test/bin/run.sh so whichever script installs
# WordPress first, the other sees an identical site (no URL mismatch).
HOST_PORT=${NRSTX_TEST_PORT:-8080}

# Authenticate composer's GitHub calls when a token is available (CI sets
# GITHUB_TOKEN automatically). Kills 429/abuse-limit install failures.
if [ -n "${GITHUB_TOKEN:-}" ] && [ -z "${COMPOSER_AUTH:-}" ]; then
  COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"${GITHUB_TOKEN}\"}}"
  export COMPOSER_AUTH
fi

echo "[INFO] Ensuring containers are up…"
$compose up -d db wordpress

echo "[INFO] Waiting 20s for DB to initialize…"
sleep 20

echo "[INFO] Installing WordPress unless installed (wp-cli needs tables)…"
$compose run --rm wpcli sh -lc "if ! wp core is-installed 2>/dev/null; then wp core install --url=localhost:${HOST_PORT} --title='NR Stocks Test' --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email; else echo 'WordPress already installed'; fi"

echo "[INFO] Generating POT file…"
mkdir -p languages
# Pre-create as the host user so the root-run container keeps host ownership.
touch languages/nr-stocks.pot
INNER='set -e; \
cd '"${PLUGIN_DIR}"'; \
if ! wp help i18n --allow-root >/dev/null 2>&1; then \
  echo "[INFO] wp i18n missing — installing i18n-command…"; \
  (command -v git >/dev/null 2>&1 || apk add --no-cache git >/dev/null); \
  tries=0; \
  while ! wp package install wp-cli/i18n-command --allow-root >/tmp/nr-i18n-install.log 2>&1; do \
    tries=$((tries + 1)); \
    if [ "$tries" -ge 3 ]; then echo "[ERROR] i18n-command install failed:" 1>&2; tail -n 5 /tmp/nr-i18n-install.log 1>&2; exit 1; fi; \
    echo "[INFO] install failed (likely rate limit); retry $tries/3…"; \
    sleep $((tries * 20)); \
  done; \
fi; \
wp i18n make-pot --allow-root . languages/nr-stocks.pot \
  --slug=nr-stocks \
  --exclude=".git,.github,.build,build,dist,vendor,test,tests,node_modules,bin,scripts,docs" \
  --headers='\''{"Report-Msgid-Bugs-To":"https://github.com/nikolareljin/gutenberg-stocks/issues"}'\'''
if [ -n "${COMPOSER_AUTH:-}" ]; then
  # shellcheck disable=SC2086
  $compose run --rm --user 0:0 -e COMPOSER_AUTH wpcli sh -lc "$INNER"
else
  $compose run --rm --user 0:0 wpcli sh -lc "$INNER"
fi

# Normalize volatile/template headers so regeneration is idempotent: the
# email-shaped dummies trip secret scanners on commit (translators fill
# real values in .po files), and the timestamp/generator would otherwise
# make the i18n bot propose a PR on every run with no string changes.
sed -i -e 's/^"Last-Translator: .*"$/"Last-Translator: FULL NAME\\n"/' \
  -e 's/^"Language-Team: .*"$/"Language-Team: LANGUAGE\\n"/' \
  -e '/^"POT-Creation-Date: /d' \
  -e 's/^"X-Generator: .*"$/"X-Generator: WP-CLI\\n"/' languages/nr-stocks.pot

echo "[DONE] languages/nr-stocks.pot updated."
