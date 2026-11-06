#!/usr/bin/env bash
# Build a distributable ZIP for WordPress.org from the current directory.
#
# Steps: npm build -> rsync per .distignore -> verify-dist (fail closed) -> zip.
# The ZIP ships build/ output AND src/ (directory guideline #4 requires the
# source of minified code to be available).
set -euo pipefail

cd "$(dirname "$0")/.."

SCRIPT_HELPERS_DIR="scripts/script-helpers"
# shellcheck source=../scripts/lib.sh
source scripts/lib.sh
nr_ensure_node_24

PLUGIN_SLUG="nr-stocks"
BUILD_DIR=".build/${PLUGIN_SLUG}"

rm -rf .build
mkdir -p "${BUILD_DIR}"
rm -f "dist/${PLUGIN_SLUG}.zip" || true

if ! command -v npm >/dev/null 2>&1; then
  echo "[ERROR] npm is required to build the block assets" 1>&2
  exit 1
fi

echo "[INFO] Installing JS dependencies and building blocks…"
npm install --no-audit --no-fund
npm run build

# Production PHP has no Composer runtime dependencies (PSR-4 autoloader in the
# bootstrap), so no vendor/ directory is built or shipped.

echo "[INFO] Syncing distributable files (respecting .distignore)…"
if [[ -f .distignore ]]; then
  rsync -av --delete --exclude-from=.distignore --exclude '.build' --exclude 'dist' ./ "${BUILD_DIR}/"
else
  rsync -av --delete ./ "${BUILD_DIR}/" --exclude ".build" --exclude "dist" --exclude ".git" --exclude "node_modules" --exclude "vendor"
fi

# Paranoia: these must never ship even if .distignore regresses.
rm -f "${BUILD_DIR}/.distignore" "${BUILD_DIR}/.gitignore" "${BUILD_DIR}/.gitmodules" || true
rm -rf "${BUILD_DIR}/test" "${BUILD_DIR}/tests" "${BUILD_DIR}/bin" "${BUILD_DIR}/scripts" || true
find "${BUILD_DIR}" -name '.DS_Store' -delete || true

# Translations must exist before packaging; try to generate them once.
if [[ ! -f "${BUILD_DIR}/languages/nr-stocks.pot" && -x bin/make-pot.sh ]]; then
  echo "[INFO] POT file missing — attempting bin/make-pot.sh…"
  bash bin/make-pot.sh || true
  if [[ -f languages/nr-stocks.pot ]]; then
    mkdir -p "${BUILD_DIR}/languages"
    cp languages/nr-stocks.pot "${BUILD_DIR}/languages/nr-stocks.pot"
  fi
fi

echo "[INFO] Verifying payload (fail closed)…"
bash bin/verify-dist.sh "${BUILD_DIR}"

mkdir -p dist
cd .build
zip -qr "../dist/${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}"
cd ..

echo "Built dist/${PLUGIN_SLUG}.zip"
