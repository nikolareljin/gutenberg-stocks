# Contributing to NR Stocks

## Development setup

- PHP 7.4+ (8.2 recommended), Node 24+, Composer, Docker.
- `./update` after cloning (syncs the `script-helpers` submodule).
- `npm install`, `composer install`.

## Before every push

```bash
scripts/lint.sh   # PHPCS (WordPress standards) + ESLint
scripts/test.sh   # Jest + PHPUnit against the WP test suite
scripts/e2e.sh    # Docker end-to-end (block, shortcode, REST, cache)
scripts/pcc.sh    # Plugin Check + header/readme/payload meta checks
```

The pre-push hook (installed via
`scripts/script-helpers/scripts/setup-hooks.sh`) scans for secrets,
private names, and broken files. Opt into push tests with
`git config script-helpers.push-tests quick`.

## Coding standards

- PHP: WordPress Coding Standards + PHPCompatibilityWP (see `phpcs.xml`),
  PHP 7.4-compatible syntax (no `match`, nullsafe, `str_contains`, …).
- JS: `@wordpress/scripts` ESLint preset (`npm run lint`).
- Text domain `nr-stocks` everywhere; user strings translatable.
- New providers: config-only (see `docs/adding-a-provider.md`) with
  fixtures + offline tests; document free tiers and display licensing.

## Versioning and releases

- `bash test/bin/version_bump.sh <major|minor|patch|X.Y.Z>` updates the
  bootstrap header, `readme.txt` Stable tag, and `package.json`.
- Add `CHANGELOG.md` + readme changelog entries in the same PR.
- Release flow: `release/X.Y.Z` PR → merge → auto-tag → GitHub Release →
  manual **Prepare WP.org Submission** → SVN deploy on tags.
- Never commit `PLAN.md` (local working state, gitignored), secrets, or
  `build/` output (built at packaging; `src/` ships per directory rules).

## WordPress.org assets

Banners, icon, and screenshots live in `.wordpress-org/` (deployed to SVN
assets, never in the plugin ZIP). Caption order must match `readme.txt`.
