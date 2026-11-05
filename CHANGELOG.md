# Changelog

All notable changes to this project are documented in this file.

## [1.0.0] - 2026-11-05

### Added

- Complete rebuild as NR Stocks for the WordPress.org directory.
- New Stock Chart block (`nr-stocks/stock-chart`) with symbol search, multi-symbol comparison overlays, daily/weekly/monthly/intraday series, point range, quote panel, and per-block cache TTL.
- Config-driven provider registry: Alpha Vantage, Twelve Data, Frankfurter (keyless forex), CoinGecko (keyless crypto), and ECB reference rates; new JSON/CSV APIs register via config or the `nr_stocks_providers` filter.
- Server-side caching with variable TTLs (presets + custom, per endpoint and per block), stampede protection, error negative-cache, purge controls, and WP-CLI commands.
- `[nr-stocks]` shortcode with server-rendered output and a no-JavaScript fallback.
- Versioned REST API (`nr-stocks/v1`: series, quote, search, providers) with cache headers and rate limits for headless frontends.
- WordPress.org packaging: `readme.txt` with external-services disclosure, `uninstall.php`, POT/i18n automation, Plugin Check + meta checks, `.distignore` + fail-closed `verify-dist`, SVN deploy workflow.
- Test suites: Jest, PHPUnit against the WP test library, and Docker end-to-end.

### Changed

- API keys and all provider traffic moved server-side; browsers only talk to your own site.
- Settings live under Settings → NR Stocks (no more top-level menu).
- Free-tier documentation corrected for current provider limits.

### Breaking

- The 0.x `dj/stock-information` block is retired. Saved frontend charts keep rendering, and settings (API token, chart color) migrate automatically — re-insert blocks as Stock Chart to restore editor previews.

## [0.1.1] - 2026-08-24

### Security
- Modernized the WordPress, React, and charting dependency graph and remediated npm audit findings.

### Changed
- Replaced the legacy stock-chart component with React Financial Charts.
- Adopted shared ci-helpers workflows for dependency audit, tests, build, and PHP syntax validation.

### Fixed
- Corrected responsive chart sizing, chart gradient handling, and parser validation coverage.

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
