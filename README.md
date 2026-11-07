# NR Stocks

Stock, forex, and crypto charts for the block editor — multiple swappable
data providers, server-side caching, a shortcode, and a headless-ready
REST API. Built for submission to WordPress.org.

`nr-stocks` is the plugin slug; this repository keeps its historical name,
`gutenberg-stocks`.

## Features

- **Stock Chart block**: symbol search, multi-symbol comparison overlays,
  daily/weekly/monthly/intraday series, point range, quote panel.
- **Providers**: Alpha Vantage, Twelve Data, Frankfurter (keyless forex),
  CoinGecko (keyless crypto), ECB reference rates — swappable per block,
  new ones addable via config (`docs/adding-a-provider.md`).
- **Caching**: per-endpoint TTL presets (1/5/15/60 min + custom),
  per-block override, object-cache aware, stampede protection, purge +
  `wp nr-stocks cache purge`.
- **Shortcode**: `[nr-stocks symbols="AAPL,MSFT" provider="twelvedata"]`
  with server-rendered output and a no-JavaScript fallback.
- **REST API**: `nr-stocks/v1` (`/series`, `/quote`, `/search`,
  `/providers`) with cache headers + rate limits (`docs/rest-api.md`).
- API keys stay on the server — browsers only talk to your own site.

## Requirements

- WordPress 6.3+ (tested to 7.1), PHP 7.4+.
- Provider keys where needed: Alpha Vantage / Twelve Data free keys;
  Frankfurter, CoinGecko demo, and ECB need none.
- Development: Node 24+, Composer, Docker (e2e), `jq`, `curl`, `zip`.

## Install (development)

```bash
git clone git@github.com:nikolareljin/gutenberg-stocks.git
cd gutenberg-stocks
./update            # git submodules (script-helpers)
npm install
composer install
```

Activate the plugin in WordPress, then visit Settings → NR Stocks.

## Use

The **default** integration is the block: its settings are a plain JSON
structure stored in post content, editable in the sidebar with a live
preview:

```html
<!-- wp:nr-stocks/stock-chart {"symbols":["AAPL","MSFT"],"provider":"twelvedata"} /-->
```

- Block (default): insert **Stock Chart**, search symbols, tune
  provider/series in the sidebar. JSON attributes stay portable,
  versionable, and work with revisions.
- Shortcode (fallback for classic content, widgets, page builders):
  `[nr-stocks symbols="EUR/USD" provider="frankfurter" quote="1"]`.
  Kept for compatibility; new content should use the block.
- Headless: `GET /wp-json/nr-stocks/v1/series?provider=…&symbols=…`.

## Develop

| Task | Command |
|---|---|
| Lint (PHP + JS) | `scripts/lint.sh` |
| Unit tests (Jest + PHPUnit) | `scripts/test.sh` |
| E2E (Docker WP) | `scripts/e2e.sh` |
| Plugin Check + meta checks | `scripts/pcc.sh` |
| Build distributable ZIP | `scripts/zip.sh` (runs `verify-dist`, fail closed) |
| Regenerate POT | `bin/make-pot.sh` |
| Bump version | `bash test/bin/version_bump.sh minor` |

Local scripts mirror CI one-to-one: `test/bin/*` is what the workflows
run. See `CONTRIBUTING.md` for the full flow and `test/README.md` for the
test stack (`NRSTX_TEST_PORT`, `NRSTX_WP_VERSION`).

## Release to WordPress.org

1. Merge a `release/X.Y.Z` PR → tag + GitHub Release are automated.
2. Run the manual **Prepare WP.org Submission** workflow → review ZIP.
3. Submit at `wordpress.org/plugins/developers/add/` (first time only).
4. The **Deploy to WordPress.org** workflow runs after the GitHub Release
   and pushes the tag to SVN (needs `SVN_USERNAME` / `SVN_PASSWORD` secrets;
   until the SVN repo exists it verifies the payload and skips the upload).
   Running **Create GitHub Release** by hand from a branch publishes the release
   but skips the deploy, so select the tag as the ref.
   A tag released before the repo existed needs one manual run: dispatch the
   workflow with that tag as the ref. Tags older than this workflow's
   `workflow_dispatch` trigger cannot be re-run, so cut a new release instead.

Docs: `docs/adding-a-provider.md`, `docs/rest-api.md`, `docs/caching.md`.
WP.org page assets live in `.wordpress-org/` (never in the ZIP).
