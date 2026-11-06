#!/usr/bin/env bash
# Regenerate languages/nr-stocks.pot (PHP + JS strings) via WP-CLI in Docker.
# Modern wp-cli bundles `wp i18n`; the i18n-command package install is only a
# fallback for images old enough to lack it (needs git in the container).
set -euo pipefail

cd "$(dirname "$0")/.."

compose="docker compose -f test/docker-compose.yml"
PLUGIN_DIR=/var/www/html/wp-content/plugins/nr-stocks

echo "[INFO] Ensuring containers are up…"
$compose up -d db wordpress

echo "[INFO] Generating POT file…"
mkdir -p languages
# Pre-create as the host user so the root-run container keeps host ownership.
touch languages/nr-stocks.pot
$compose run --rm --user 0:0 wpcli sh -lc 'set -e; \
cd '"${PLUGIN_DIR}"'; \
if ! wp help i18n --allow-root >/dev/null 2>&1; then \
  echo "[INFO] wp i18n missing — installing i18n-command…"; \
  (command -v git >/dev/null 2>&1 || apk add --no-cache git >/dev/null); \
  wp package install wp-cli/i18n-command --allow-root >/dev/null; \
fi; \
wp i18n make-pot --allow-root . languages/nr-stocks.pot \
  --slug=nr-stocks \
  --exclude=".git,.github,.build,build,dist,vendor,test,tests,node_modules,bin,scripts,docs" \
  --headers='\''{"Report-Msgid-Bugs-To":"https://github.com/nikolareljin/gutenberg-stocks/issues"}'\'''

# Normalize gettext template placeholders (email-shaped dummies trip
# secret scanners on commit); translators fill real values in .po files.
sed -i -e 's/^"Last-Translator: .*"$/"Last-Translator: FULL NAME\\n"/' -e 's/^"Language-Team: .*"$/"Language-Team: LANGUAGE\\n"/' languages/nr-stocks.pot

echo "[DONE] languages/nr-stocks.pot updated."
