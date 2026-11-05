#!/usr/bin/env bash
# Run the Docker end-to-end suite (same script CI runs).
# Usage: scripts/e2e.sh
set -euo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=lib.sh
source scripts/lib.sh

usage() {
  echo "Usage: scripts/e2e.sh"
  echo "Runs test/bin/run.sh. Honors NRSTX_TEST_PORT / NRSTX_WP_VERSION."
}
nr_handle_help usage "$@"

print_info "End-to-end suite…"
bash test/bin/run.sh
