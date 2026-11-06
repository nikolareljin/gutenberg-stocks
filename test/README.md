Test Environment for NR Stocks
==============================

What this does
-------------
- Spins up WordPress (+ MariaDB) on http://localhost:8080 with this plugin mounted.
- Installs WordPress (single site), activates the plugin, sets the keyless
  Frankfurter provider as default.
- Creates fixture posts carrying the block and the shortcode, then asserts
  their frontend output, the REST API, rate limiting, WP-CLI, and cache HITs.
- `plugin-check.sh` rebuilds a distributable copy (what WordPress.org gets)
  and runs the official Plugin Check plus header/readme/payload meta checks.

Requirements
------------
- Docker + Docker Compose plugin, Node 22 + npm (for the block build), curl, jq.

Port / version configuration
----------------------------
- `NRSTX_TEST_PORT` (default 8080) — host port.
- `NRSTX_WP_VERSION` (default latest) — WordPress image tag, e.g. `6.3`.
- `NRSTX_PLUGIN_SRC` — override the mounted plugin source (used by
  plugin-check.sh to mount the dist copy). `bin/make-pot.sh` does not use
  this stack; it runs in the bare WP-CLI image.

Quick start
-----------
1) Full end-to-end: `bash test/bin/run.sh`
2) Plugin Check on the dist payload: `bash test/bin/plugin-check.sh`
3) Lint PHP: `bash test/bin/phpcs.sh` (add `--fix` to auto-fix)
4) Tear down: `bash test/bin/down.sh`

Network note
------------
Series assertions hit the live keyless Frankfurter API. If the upstream is
unreachable, data assertions downgrade to error-envelope checks and the run
says so — the contract is always verified, live data opportunistically.
