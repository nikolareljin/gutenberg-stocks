#!/usr/bin/env bash
# Run unit tests: Jest (JS) + PHPUnit (WP suite, provisioned like CI does).
# Usage: scripts/test.sh [--js-only|--php-only]
set -euo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=lib.sh
source scripts/lib.sh

usage() {
  echo "Usage: scripts/test.sh [--js-only|--php-only]"
  echo "Jest on the host; PHPUnit through script-helpers ci_wp_phpunit.sh"
  echo "(same provisioner CI uses) or a local WP_TESTS_DIR."
}
nr_handle_help usage "$@"

MODE="all"
for arg in "$@"; do
  case "${arg}" in
    --js-only) MODE="js" ;;
    --php-only) MODE="php" ;;
  esac
done

fail=0

if [ "${MODE}" = "all" ] || [ "${MODE}" = "js" ]; then
  nr_ensure_node_24
  if [ ! -d node_modules ]; then
    print_info "Installing JS dependencies…"
    npm ci --no-audit --no-fund
  fi
  print_info "Jest…"
  npm test -- --ci || fail=1
fi

if [ "${MODE}" = "all" ] || [ "${MODE}" = "php" ]; then
  if [ ! -f vendor/autoload.php ]; then
    if command -v composer >/dev/null 2>&1; then
      print_info "Installing PHP dependencies…"
      composer install --no-interaction --prefer-dist
    else
      print_error "composer not found and vendor/ missing."
      exit 1
    fi
  fi
  HELPER="scripts/script-helpers/scripts/ci_wp_phpunit.sh"
  if [ -x "${HELPER}" ] || [ -f "${HELPER}" ]; then
    print_info "PHPUnit via ci_wp_phpunit.sh (same as CI)…"
    bash "${HELPER}" \
      --wp-version "${NRSTX_WP_VERSION:-latest}" \
      --db-image "${NRSTX_DB_IMAGE:-mariadb:10.11}" \
      --workdir "." \
      --test-command "./vendor/bin/phpunit" || fail=1
  elif [ -n "${WP_TESTS_DIR:-}" ]; then
    print_info "PHPUnit with local WP_TESTS_DIR…"
    ./vendor/bin/phpunit || fail=1
  else
    print_error "No WP test library. Run ./update (script-helpers) or set WP_TESTS_DIR."
    exit 1
  fi
fi

if [ "${fail}" -ne 0 ]; then
  print_error "Tests failed."
  exit 1
fi
print_success "All tests passed."
