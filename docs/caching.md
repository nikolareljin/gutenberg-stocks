# Caching

Upstream market data is cached per provider, endpoint, and parameters, so a
busy site hammers neither quotas nor page latency. First paint needs no
fetch at all: the block/shortcode render bakes data into the markup.

## TTL layers (highest priority first)

1. Per-request override: block attribute `cacheTtl` / shortcode `ttl=""` /
   REST `ttl` param for logged-in editors (seconds, `0` = inherit).
2. Saved per-endpoint setting: Settings → NR Stocks → Caching.
3. Built-in defaults: series 300s (5 min), quote 60s (1 min), search 3600s.

Presets used across the UI: 60 (1 min), 300 (5 min), 900 (15 min), 3600
(1 hour), plus any custom 0..86400 value. `0` disables caching for that
endpoint (not recommended in production).

## How it works

- Storage: Transients API, keyed
  `nrstx_{provider}_{endpoint}_{md5(params)}`. Hosts with Redis/Memcached
  get persistent, shared caching for free; the settings screen reports
  whether one was detected.
- Stampede protection: a 15s fetch mutex (atomic `wp_cache_add` under
  object caching, advisory transient otherwise, so a tight race can still
  fetch twice). Parallel cold requests wait up to 13s for the fetch in
  progress (`nr_stocks_lock_wait_ms` filter), then report `busy`.
- Ranges share entries: upstream requests use 30, 90, 180, or 365 points and
  each response is sliced to the requested `range`. Each caller still applies
  its own TTL, so a long per-block TTL does not freeze other charts.
- Entries outlive their TTL by one hour. A fresh read still honors the TTL, but
  an older entry is served when a refresh fails, is over budget, or is already
  in progress. After a failed refresh, upstream is left alone for 60s. A
  short-TTL refresh never shortens an entry's lifetime.
- A page holding a temporary failure tells page caches not to store it.
- Errors cached 60s (negative cache), so a dead upstream degrades instead
  of melting quotas.
- Purge: the settings button, saving settings, `wp nr-stocks cache purge`,
  or `Cache::purge_all()` (key index + options-table fallback; never a
  global object-cache flush).
- HTTP: REST answers carry `Cache-Control: max-age=<ttl>`, an `ETag`, and
  `X-NR-Stocks-Cache: HIT|MISS`. The frontend JS dedupes identical requests
  while they are in flight.

## High-traffic recipe

1. Add a persistent object cache (Redis/Memcached) — entries then survive
   across requests instead of living in the options table.
2. Raise series TTL toward 900s and quote TTL toward 300s if slightly
   older data is acceptable; keep `Cache-Control` in mind for CDN layers.
3. Warm important symbols after deploys:
   `wp nr-stocks fetch --provider=… --symbol=…` (also useful in cron).
4. Keep the REST rate limit on (default 60/min/IP), size the anonymous
   upstream budget (default 30/min) to your provider's quota, and keep the
   CORS allowlist tight; all three live under Settings → NR Stocks → REST API.
