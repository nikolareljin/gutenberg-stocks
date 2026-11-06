# Shared loader for scripts/*.sh — shellcheck shell=bash
#
# Sources script-helpers when the submodule is present (see ./update) and
# falls back to plain echo helpers otherwise, so every wrapper also runs on
# a fresh checkout.

# shellcheck disable=SC2034
SHLIB_AVAILABLE=0

nr_scripts_dir() {
  cd "$(dirname "${BASH_SOURCE[1]}")" && pwd
}

SCRIPT_HELPERS_DIR="${SCRIPT_HELPERS_DIR:-$(nr_scripts_dir)/script-helpers}"

if [ -f "${SCRIPT_HELPERS_DIR}/helpers.sh" ]; then
  # shellcheck source=/dev/null
  source "${SCRIPT_HELPERS_DIR}/helpers.sh"
  if shlib_import logging 2>/dev/null; then
    SHLIB_AVAILABLE=1
  fi
fi

if [ "${SHLIB_AVAILABLE}" -eq 0 ]; then
  print_info() { echo "[INFO] $*"; }
  print_error() { echo "[ERROR] $*" 1>&2; }
  print_success() { echo "[OK] $*"; }
  print_warning() { echo "[WARN] $*"; }
  log_info() { echo "[INFO] $*"; }
  log_error() { echo "[ERROR] $*" 1>&2; }
fi

nr_project_root() {
  cd "$(nr_scripts_dir)/.." && pwd
}

# Ensure node >= 24 (see .nvmrc / package.json engines). Falls back to nvm
# when the ambient node is too old; fails closed otherwise.
nr_ensure_node_24() {
  local major pin
  major="$(node -p "process.versions.node.split('.')[0]" 2>/dev/null || echo 0)"
  if [ "${major}" -ge 24 ] 2>/dev/null; then
    return 0
  fi
  pin="24"
  if [ -f "$(nr_project_root)/.nvmrc" ]; then
    pin="$(tr -d '[:space:]' < "$(nr_project_root)/.nvmrc")"
  fi
  # Always source the real nvm: an unrelated `nvm` shim may already sit on
  # PATH (e.g. the npm `nvm` package) and it cannot switch this shell.
  export NVM_DIR="${NVM_DIR:-${HOME}/.nvm}"
  if [ -s "${NVM_DIR}/nvm.sh" ]; then
    # nvm.sh is not guaranteed nounset-clean; relax around the source.
    set +u
    # shellcheck source=/dev/null
    . "${NVM_DIR}/nvm.sh" --no-use
    set -u
  fi
  # The real nvm is a shell function; anything else is a useless shim.
  if [ "$(command -v nvm 2>/dev/null)" = "nvm" ]; then
    print_info "Activating node ${pin} via nvm (ambient node major is ${major})…"
    nvm use "${pin}" >/dev/null 2>&1 || nvm install "${pin}" >/dev/null 2>&1 || true
    major="$(node -p "process.versions.node.split('.')[0]" 2>/dev/null || echo 0)"
    if [ "${major}" -ge 24 ] 2>/dev/null; then
      return 0
    fi
  fi
  print_error "node >= 24 is required (have major ${major}); see .nvmrc."
  return 1
}

# Minimal -h/--help handling shared by all wrappers.
nr_handle_help() {
  local usage_fn="$1"; shift
  for arg in "$@"; do
    if [ "${arg}" = "-h" ] || [ "${arg}" = "--help" ]; then
      "${usage_fn}"
      exit 0
    fi
  done
}
