#!/usr/bin/env bash
# Run all linters: PHPCS (WordPress standards) + ESLint (block JS).
# Usage: scripts/lint.sh [--php-only|--js-only]
set -euo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=lib.sh
source scripts/lib.sh

usage() {
  echo "Usage: scripts/lint.sh [--php-only|--js-only]"
  echo "Runs PHPCS and ESLint. Prefers host tooling, falls back to Docker for PHP."
}
nr_handle_help usage "$@"

MODE="all"
for arg in "$@"; do
  case "${arg}" in
    --php-only) MODE="php" ;;
    --js-only) MODE="js" ;;
  esac
done

fail=0

if [ "${MODE}" = "all" ] || [ "${MODE}" = "php" ]; then
  if [ -x vendor/bin/phpcs ]; then
    print_info "PHPCS on host…"
    vendor/bin/phpcs -p -s . || fail=1
  else
    print_info "No host vendor/bin/phpcs — running in Docker…"
    bash test/bin/phpcs.sh || fail=1
  fi
fi

if [ "${MODE}" = "all" ] || [ "${MODE}" = "js" ]; then
  nr_ensure_node_24
  if [ ! -d node_modules ]; then
    print_info "Installing JS dependencies…"
    npm ci --no-audit --no-fund
  fi
  print_info "ESLint…"
  npm run lint || fail=1
fi

if [ "${fail}" -ne 0 ]; then
  print_error "Lint failed."
  exit 1
fi
print_success "Lint clean."
