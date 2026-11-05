#!/usr/bin/env bash
# Run Plugin Check + meta checks on the distributable copy (same as CI).
# Usage: scripts/pcc.sh
set -euo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=lib.sh
source scripts/lib.sh

usage() {
  echo "Usage: scripts/pcc.sh"
  echo "Runs test/bin/plugin-check.sh against a fresh dist build."
}
nr_handle_help usage "$@"

print_info "Plugin Check on dist payload…"
bash test/bin/plugin-check.sh
