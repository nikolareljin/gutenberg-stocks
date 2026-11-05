# REST API reference (`nr-stocks/v1`)

Read-only JSON for the block editor and headless/decoupled frontends. Same
normalized envelopes everywhere; same cache and rate limits.

Base: `https://example.com/wp-json/nr-stocks/v1`

## Common behavior

- Methods: `GET` only. Auth: none (public read). Throttling: per-IP +
  route transient bucket, default 60/min (setting 0..3600, 0 = off);
  over-limit answers are `nr_stocks_rate_limited` (HTTP 429) with a
  `retry_after` hint.
- Headers on success: `Cache-Control: public, max-age=<ttl>`,
  `ETag: "<md5>"`, `X-NR-Stocks-Cache: HIT|MISS`.
- CORS: same-origin always works. Cross-origin browsers are allowed only
  for hosts listed under Settings → NR Stocks → REST API.
- Params: `provider` (id, default = saved default), `symbols` CSV and/or
  `symbol` (max 5, cleaned server-side), `series` (daily|weekly|monthly|
  intraday), `range` 1..365 (default 30), `interval` (intraday only),
  `ttl` override in seconds (0 = site default).

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
http_error, upstream_error, upstream_limit, parse_error).

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

- 200 — envelope. Series/quote keep failures per-symbol (`{ok:false,
  error}` inside `data`) so partial results still return.
- 400 — `nr_stocks_bad_symbol`, `nr_stocks_unknown_provider`,
  `nr_stocks_unsupported_*`, `rest_invalid_param`.
- 429 — `nr_stocks_rate_limited` (ours) or `nr_stocks_upstream_limit`.
- 503 — `nr_stocks_missing_key` (search route; series/quote report it
  per-symbol).
- 502 — upstream/parse failures on the search route.
