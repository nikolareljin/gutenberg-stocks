#!/usr/bin/env bash
# Regenerate languages/nr-stocks.pot (PHP + JS strings).
# `wp i18n make-pot` needs no database or WordPress install, so this runs in
# the bare WP-CLI image. Line references and volatile headers are left out so
# the file only changes when strings change.
set -euo pipefail

cd "$(dirname "$0")/.."

IMAGE="${NRSTX_WPCLI_IMAGE:-wordpress:cli}"
TARGET="languages/nr-stocks.pot"
OUT_DIR="$(mktemp -d)"
trap 'rm -rf "${OUT_DIR}"' EXIT

echo "[INFO] Generating POT file…"
docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e HOME=/tmp \
  -v "$(pwd):/app:ro" \
  -v "${OUT_DIR}:/out" \
  -w /app \
  "${IMAGE}" \
  wp i18n make-pot . /out/nr-stocks.pot \
  --slug=nr-stocks \
  --no-location \
  --exclude=".git,.github,.build,build,dist,vendor,test,tests,node_modules,bin,scripts,docs" \
  --headers='{"Report-Msgid-Bugs-To":"https://github.com/nikolareljin/gutenberg-stocks/issues"}'

# Email-shaped gettext placeholders trip secret scanners; the timestamp and
# generator version would change the file on every run.
sed -e 's/^"Last-Translator: .*"$/"Last-Translator: FULL NAME\\n"/' \
  -e 's/^"Language-Team: .*"$/"Language-Team: LANGUAGE\\n"/' \
  -e '/^"POT-Creation-Date: /d' \
  -e 's/^"X-Generator: .*"$/"X-Generator: WP-CLI\\n"/' \
  "${OUT_DIR}/nr-stocks.pot" > "${OUT_DIR}/normalized.pot"

# Only a complete result replaces the committed file.
if [ "$(grep -c '^msgid ' "${OUT_DIR}/normalized.pot")" -lt 10 ]; then
  echo "[ERROR] Generated POT is incomplete; ${TARGET} left unchanged." 1>&2
  exit 1
fi
mkdir -p languages
cat "${OUT_DIR}/normalized.pot" > "${TARGET}"

echo "[DONE] ${TARGET} updated."
