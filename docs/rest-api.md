# REST API reference (`nr-stocks/v1`)

Read-only JSON for the block editor and headless/decoupled frontends. Same
normalized envelopes everywhere; same cache and rate limits.

Base: `https://example.com/wp-json/nr-stocks/v1`

## Common behavior

- Methods: `GET` only. Auth: none (public read). Throttling: per-IP +
  route fixed window, default 60/min (setting 0..3600, 0 = off);
  over-limit answers are `nr_stocks_rate_limited` (HTTP 429) with a
  `retry_after` hint. Behind a proxy or CDN, return the visitor address from
  the `nr_stocks_client_ip` filter; forwarded headers are not trusted by
  default.
- Upstream budget: logged-out requests share a site-wide budget of cache
  misses, default 30/min (setting 0..3600, 0 = off). Past it, uncached
  symbols report `upstream_budget`. Cached data, including entries past their
  normal TTL, keeps being served. The budget protects your provider quota for
  the whole site, so one client can use it up for that minute; the block and
  shortcode render on the server and are not limited by it.
- Headers on success for logged-out requests:
  `Cache-Control: public, max-age=<ttl>` (`no-store` when any symbol failed;
  core sends its own no-cache headers to logged-in users),
  `ETag: "<md5>"`, `X-NR-Stocks-Cache: HIT|MISS`.
- CORS: same-origin always works. Cross-origin browsers are allowed only
  for hosts listed under Settings → NR Stocks → REST API, without
  credentials. This applies to `nr-stocks/v1` routes only, where JSONP is also
  disabled.
- Params: `provider` (id, default = saved default), `symbols` CSV and/or
  `symbol` (max 5, cleaned server-side), `series` (daily|weekly|monthly|
  intraday), `range` 1..365 (default 30), `interval` (intraday only;
  unknown values use 15min), `ttl` override in seconds (0 = site default;
  honored only for logged-in users who can edit posts).

## `GET /providers`

Public descriptors. Never includes keys or internal hosts.

```json
{
  "data": [
    {
      "id": "twelvedata",
      "label": "Twelve Data",
      "needs_key": true,
      "has_key": true,
      "series": { "daily": ["equity", "forex", "crypto"] },
      "supports": { "quote": true, "search": true },
      "rate_note": "Free: 8 credits/min, 800/day, internal non-display use."
    }
  ],
  "meta": { "default": "alphavantage" }
}
```

## `GET /series`

```bash
curl 'https://example.com/wp-json/nr-stocks/v1/series?provider=frankfurter&symbols=EUR/USD&range=5'
```

```json
{
  "data": {
    "EUR/USD": {
      "ok": true,
      "data": [
        { "date": "2026-10-01", "open": null, "high": null, "low": null,
          "close": 1.1326, "volume": null }
      ],
      "cached": true,
      "fetched_at": 1730000000,
      "meta": { "provider": "frankfurter", "symbol": "EUR/USD",
        "asset": "forex", "series": "daily", "ttl": 300 }
    }
  },
  "meta": { "provider": "frankfurter", "series": "daily", "ttl": 300 }
}
```

Per-symbol failures stay per-symbol: `{ok:false, error:"upstream_limit"}`
with the machine-readable codes from `Rest_Provider` (unknown_provider,
missing_key, bad_symbol, unsupported_series/asset/pair, forbidden_host,
http_error, upstream_error, upstream_limit, upstream_budget, busy,
parse_error). `busy` means another request is already fetching the same data
and did not finish in time; retry shortly.

## `GET /quote`

```bash
curl 'https://example.com/wp-json/nr-stocks/v1/quote?provider=twelvedata&symbol=AAPL'
```

```json
{
  "data": {
    "AAPL": {
      "ok": true,
      "data": {
        "symbol": "AAPL", "name": "Apple Inc.", "price": 347.15,
        "open": 353.905, "high": 355.38, "low": 347.1, "volume": 927290,
        "previous_close": 349.51, "change": -2.36,
        "change_percent": -0.675, "date": "2026-11-05", "currency": "USD"
      },
      "cached": false,
      "fetched_at": 1730000000
    }
  },
  "meta": { "provider": "twelvedata", "ttl": 60 }
}
```

Providers without a quote endpoint synthesize one from the latest series
points (`meta.synthetic: true`).

## `GET /search`

```bash
curl 'https://example.com/wp-json/nr-stocks/v1/search?provider=twelvedata&q=apple'
```

```json
{
  "data": [
    { "symbol": "AAPL", "name": "Apple Inc.", "exchange": "NASDAQ",
      "type": "Common Stock", "currency": "USD" }
  ],
  "meta": { "provider": "twelvedata", "ttl": 3600, "fetched_at": 1730000000 }
}
```

Up to 10 matches.

## HTTP status mapping

- Errors send `Cache-Control: no-store`; rate limited answers add
  `Retry-After`.
- 200 — envelope. Series/quote keep failures per-symbol (`{ok:false,
  error}` inside `data`) so partial results still return.
- 400 — `nr_stocks_bad_symbol`, `nr_stocks_unknown_provider`,
  `nr_stocks_unsupported_*`, `rest_invalid_param`.
- 429 — `nr_stocks_rate_limited` (ours), `nr_stocks_upstream_limit`, or
  `nr_stocks_upstream_budget` (search route).
- 503 — `nr_stocks_missing_key` or `nr_stocks_busy` (search route;
  series/quote report them per-symbol).
- 502 — upstream/parse failures on the search route.
