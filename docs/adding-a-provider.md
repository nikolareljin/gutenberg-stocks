# Adding a data provider (no new classes)

Providers are configuration consumed by `inc/Providers/Rest_Provider.php`.
A JSON or CSV API needs only a config array — ship it in
`Configs::all()`, or register it from a theme/another plugin through the
`nr_stocks_providers` filter.

## Config schema

```php
array(
    'id'          => 'myapi',            // unique, sanitize_key-safe
    'label'       => 'My API',           // settings UI + REST descriptors
    'description' => '…',                // one-liner for docs/UI
    'needs_key'   => true,               // adds a key field in settings
    'key_setting' => 'myapi',            // key name under settings['keys']
    'key_url'     => 'https://…',        // "get a key" link (or '')
    'hosts'       => array( 'https://api.myapi.test' ), // SSRF allowlist
    'series'      => array(
        'daily' => array(
            array(
                'assets' => array( 'equity' ), // subset of equity/forex/crypto
                'url'    => '{host}/bars?symbol={symbol}&apikey={key}',
                'format' => 'json',            // or 'csv'
                'rows'   => 'data',            // JSON key, or null = body is rows
                'order'  => 'desc',            // normalized to oldest-first
                'map'    => array(             // canonical => source key
                    'date'   => 't',
                    'open'   => 'o',
                    'high'   => 'h',
                    'low'    => 'l',
                    'close'  => 'c',
                    'volume' => 'v',
                ),
            ),
        ),
    ),
    'quote'       => null,                // or a single-row op (same schema)
    'search'      => null,                // or a search op (same schema)
    'rate_note'   => 'Free: …',           // shown in UI/docs
)
```

URL placeholders (substituted + encoded): `{host} {symbol} {from} {to}
{market} {range} {interval} {cur} {cg_id} {from_date} {to_date} {q} {key}`.
`{from}/{to}` come from splitting `BASE/QUOTE` symbols.

Special op keys for awkward shapes:

- `keyed_by`: param whose value selects the row from a keyed object
  (CoinGecko spot: `{"bitcoin": {...}}` with `keyed_by: cg_id`).
- `pairs: {path, ts: ms|s, value}`: `[[timestamp, value]]` rows.
- `local_filter: true` + `local_list`: filter a static list, no HTTP
  (CoinGecko majors search).
- `local_filter: true` without a list: fetch once, normalize a catalog,
  filter locally (Frankfurter currencies).
- `parser: [class, method]`: full custom parse receiving
  `($decoded, $params)` and returning canonical rows (ECB SDMX).
- `meta_paths: {currency: 'meta.currency'}`: dot paths read from the
  top-level body into the series meta.
- `error_paths: ['Note', 'message']`: JSON keys signalling upstream
  errors (messages mentioning rate/limit/premium map to `upstream_limit`).
- `transforms: {change_percent: 'percent'}`: strips `%` and casts float.
- `base_currency: 'EUR'`: rejects pairs not based on it, before any HTTP.

## Worked example: Twelve Data weekly bars

Twelve Data ships only the verified `1day` interval. Once `interval=1week`
is confirmed against a live key, the whole feature is one config entry:

```php
'weekly' => array(
    array(
        'assets' => array( 'equity', 'forex', 'crypto' ),
        'url'    => '{host}/time_series?symbol={symbol}&interval=1week'
            . '&outputsize={range}&apikey={key}',
        'format' => 'json',
        'rows'   => 'values',
        'order'  => 'desc',
        'map'    => $ohlcv, // same map as daily
        'meta_paths'  => array(
            'currency' => 'meta.currency',
            'exchange' => 'meta.exchange',
        ),
        'error_paths' => array( 'message' ),
    ),
),
```

The block's series picker, settings, REST validation, and cache keys pick
it up automatically (`describe()` drives the UI).

## Adding via filter (no fork)

```php
add_filter( 'nr_stocks_providers', function ( $providers ) {
    $providers['myapi'] = array( /* schema above */ );
    return $providers;
} );
```

Use `nr_stocks_provider_config_{id}` to override one provider (hosts,
TTLs, endpoints) without replacing the registry.

## Checklist for a new provider

1. Verify every endpoint against the official docs or a live call; save
   trimmed fixtures under `tests/phpunit/fixtures/`.
2. Add URL-shape + parser assertions to `RestProviderTest.php` (offline,
   via the `pre_http_request` mock).
3. Document the free tier + display-licensing terms in `rate_note`,
   `readme.txt` (External Services), and `WPORG_PAGE.md` if user-facing.
4. Confirm the SSRF allowlist (`hosts`) covers exactly the hosts used.
