# Changelog

All notable changes to this project are documented in this file.

## [0.1.0] - 2026-02-13

### Security
- Upgraded legacy build dependencies and refreshed npm lockfile to address reported package vulnerabilities.
- Replaced the old `cgb-scripts` toolchain with `@wordpress/scripts` and updated dependency tree.

### Changed
- Bumped project version to `0.1.0` in:
  - `package.json` / `package-lock.json`
  - `plugin.php` (WordPress plugin header)
  - `composer.json`
- Migrated block build output from `dist/` to `build/`.
- Updated block asset registration in `src/init.php` to use generated `build/index.asset.php`.
- Added Node 20 baseline in package engines.

### Fixed
- Fixed CI workflow to run on Node 20 and use `npm ci --legacy-peer-deps` for current React 16 compatibility.
- Replaced obsolete GitHub Actions workflow with a build-focused CI workflow.
- Removed broken import in block save logic and fixed shared SCSS variable usage.
