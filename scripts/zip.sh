#!/usr/bin/env bash
# Build the distributable ZIP (fail closed via verify-dist).
# Usage: scripts/zip.sh
set -euo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=lib.sh
source scripts/lib.sh

usage() {
  echo "Usage: scripts/zip.sh"
  echo "Runs bin/build-zip.sh (npm build + rsync + verify + zip)."
}
nr_handle_help usage "$@"

print_info "Building distributable ZIP…"
bash bin/build-zip.sh
