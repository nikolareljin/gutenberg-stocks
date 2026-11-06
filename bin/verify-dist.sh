#!/usr/bin/env bash
# Fail closed if the distributable payload contains anything it should not.
#
# Usage: bash bin/verify-dist.sh [dist-dir]
# Exit 0 only when the payload is clean. This runs at the end of
# bin/build-zip.sh, in CI on every PR, and before any WordPress.org deploy.
set -euo pipefail

cd "$(dirname "$0")/.."

DIST_DIR="${1:-.build/nr-stocks}"
MANIFEST="dist/nr-stocks-manifest.txt"

fail=0
say() { echo "[verify-dist] $*"; }
violation() { echo "[verify-dist] VIOLATION: $*" 1>&2; fail=1; }

if [ ! -d "${DIST_DIR}" ]; then
  echo "[verify-dist] dist dir '${DIST_DIR}' missing — run bin/build-zip.sh first" 1>&2
  exit 1
fi

# 1. Forbidden top-level entries (dev tooling, VCS, secrets-adjacent files).
for entry in .git .github .gitignore .gitmodules .distignore node_modules vendor \
    test tests __tests__ bin scripts docs .build dist .wordpress-org .idea .vscode \
    composer.json composer.lock package.json package-lock.json phpcs.xml \
    phpunit.xml.dist .eslintrc.json .eslintignore .gitleaks.toml .env PLAN.md jest-unit.config.js .nvmrc \
    CONTRIBUTING.md postcss.config.js update; do
  if [ -e "${DIST_DIR}/${entry}" ]; then
    violation "forbidden entry shipped: ${entry}"
  fi
done

# 2. No hidden files anywhere (WordPress.org rejects them; Plugin Check flags them).
dotfiles="$(find "${DIST_DIR}" -name '.*' -not -name '.' -not -name '..' | head -n 20 || true)"
if [ -n "${dotfiles}" ]; then
  violation "hidden files shipped: $(echo "${dotfiles}" | tr '\n' ' ')"
fi

# 3. No symlinks (they do not survive ZIP/SVN round-trips intact).
links="$(find "${DIST_DIR}" -type l | head -n 20 || true)"
if [ -n "${links}" ]; then
  violation "symlinks shipped: $(echo "${links}" | tr '\n' ' ')"
fi

# 4. No dev test files, stray logs, backups, or editor droppings.
strays="$(find "${DIST_DIR}" \( -name '*.test.js' -o -name '*.log' -o -name '*.bak' -o -name '*~' -o -name '*.orig' -o -name '*.rej' -o -name '.DS_Store' -o -name 'Thumbs.db' \) | head -n 20 || true)"
if [ -n "${strays}" ]; then
  violation "stray files shipped: $(echo "${strays}" | tr '\n' ' ')"
fi

# 5. Required files must be present (bootstrap, readme, uninstall, translations,
#    built block, AND the JS source the build came from — directory guideline #4).
for required in nr-stocks.php readme.txt uninstall.php languages/nr-stocks.pot \
    build/blocks/stock-info/block.json src/blocks/stock-info/block.json \
    src/blocks/stock-info/index.js inc/Plugin.php; do
  if [ ! -e "${DIST_DIR}/${required}" ]; then
    violation "required file missing: ${required}"
  fi
done

# An empty POT would pass the existence check above.
if [ ! -s "${DIST_DIR}/languages/nr-stocks.pot" ]; then
  violation "languages/nr-stocks.pot is empty"
fi

# 6. Version consistency across the four manifests.
bootstrap_version="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "${DIST_DIR}/nr-stocks.php" | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/' || true)"
stable_tag="$(grep -m1 -E '^Stable tag:' "${DIST_DIR}/readme.txt" | sed -E 's/.*Stable tag:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/' || true)"
if [ -z "${bootstrap_version}" ] || [ -z "${stable_tag}" ]; then
  violation "could not parse Version header or Stable tag"
elif [ "${bootstrap_version}" != "${stable_tag}" ]; then
  violation "Version header (${bootstrap_version}) != Stable tag (${stable_tag})"
fi

# 7. Payload size sanity (a node_modules/vendor leak would blow past this).
size_kb="$(du -sk "${DIST_DIR}" | cut -f1)"
if [ "${size_kb}" -gt 10240 ]; then
  violation "payload is ${size_kb}KB (limit 10240KB) — did dev files leak in?"
fi

# 8. Secrets scan of the exact payload. Gitleaks when available; without it the
#    repo-wide gitleaks-scan workflow is the backstop (CI always runs it).
if command -v gitleaks >/dev/null 2>&1; then
  if ! gitleaks detect --source "${DIST_DIR}" --no-git --exit-code 1 --log-level warn >/dev/null 2>&1; then
    violation "gitleaks reported findings in the payload (run: gitleaks detect --source ${DIST_DIR} --no-git)"
  fi
else
  say "WARNING: gitleaks not installed locally — relying on CI scan"
fi

# 9. Write the file manifest for review artifacts.
mkdir -p dist
find "${DIST_DIR}" -type f | sed "s|^${DIST_DIR}/||" | LC_ALL=C sort > "${MANIFEST}"
count="$(wc -l < "${MANIFEST}" | tr -d ' ')"
say "manifest: ${MANIFEST} (${count} files, ${size_kb}KB payload)"

if [ "${fail}" -ne 0 ]; then
  echo "[verify-dist] FAILED — payload rejected" 1>&2
  exit 1
fi
say "OK — payload clean"
