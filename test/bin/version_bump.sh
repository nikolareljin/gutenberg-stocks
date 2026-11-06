#!/usr/bin/env bash
# Bump the plugin version across manifests (bootstrap header, readme Stable
# tag, package.json). Usage: bash test/bin/version_bump.sh <major|minor|patch|X.Y.Z>
set -euo pipefail

usage() {
  echo "Usage: $0 <major|minor|patch|X.Y.Z>"
}

if [[ $# -ne 1 ]]; then
  usage
  exit 1
fi

is_semver=0
if [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  is_semver=1
else
  case "$1" in
    major|minor|patch) ;;
    *)
      usage
      exit 1
      ;;
  esac
fi

plugin_file="nr-stocks.php"
readme_file="readme.txt"
package_file="package.json"

version_line="$(grep -n "^ \* Version:" "$plugin_file" | head -n1)"
current_version="$(echo "${version_line}" | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"

if [[ -z "${current_version}" ]]; then
  echo "Could not find Version in ${plugin_file}"
  exit 1
fi

if [[ "${is_semver}" -eq 1 ]]; then
  new_version="$1"
else
  IFS='.' read -r major minor patch <<< "${current_version}"

  case "$1" in
    major)
      major=$((major + 1))
      minor=0
      patch=0
      ;;
    minor)
      minor=$((minor + 1))
      patch=0
      ;;
    patch)
      patch=$((patch + 1))
      ;;
  esac

  new_version="${major}.${minor}.${patch}"
fi

sed -i -E "s/^(\\s*\\* Version:[[:space:]]*).*/\\1${new_version}/" "${plugin_file}"
sed -i -E "s/^(Stable tag:[[:space:]]*).*/\\1${new_version}/" "${readme_file}"
sed -i -E "s/^([[:space:]]*\"version\":[[:space:]]*\")[0-9]+\.[0-9]+\.[0-9]+(\".*)/\\1${new_version}\\2/" "${package_file}"

echo "Version bumped: ${current_version} -> ${new_version}"
echo "Remember to add a CHANGELOG.md + readme.txt changelog entry."
