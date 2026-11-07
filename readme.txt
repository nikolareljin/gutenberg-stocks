=== NR Stocks ===
Contributors: nreljin
Donate link: https://profiles.wordpress.org/nreljin/
Tags: stocks, charts, forex, crypto, block
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stock, forex, and crypto charts for the block editor. Multiple data providers, server-side caching, shortcode, and a headless-ready REST API.

== Description ==

NR Stocks renders market charts — stocks, ETFs, forex pairs, and cryptocurrencies — as a Gutenberg block or shortcode.

Features:

* Chart block with symbol search, multi-symbol comparison overlays, daily/weekly/monthly/intraday series, and an optional quote panel
* Multiple data providers behind one interface: Alpha Vantage, Twelve Data, Frankfurter (keyless forex), CoinGecko (keyless crypto), and the ECB reference rates
* Server-side caching with per-endpoint TTL presets (1 minute to 1 hour) plus custom values, stampede protection, and one-click purge — built for high-traffic sites
* `[nr-stocks]` shortcode with server-rendered output and a no-JavaScript fallback
* Versioned REST API (`nr-stocks/v1`) so headless or decoupled frontends can consume the same data
* New providers can be added later through configuration and the `nr_stocks_providers` filter — see the developer docs

Your API keys stay on the server: the browser only talks to your own site, which fetches and caches upstream data.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/nr-stocks` directory, or install via the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings → NR Stocks, pick a provider, and enter an API key if the provider needs one (Frankfurter, CoinGecko demo, and ECB need no key).
4. Insert the "Stock Chart" block into any post or page, or use the `[nr-stocks symbols="AAPL,MSFT"]` shortcode.

== Frequently Asked Questions ==

= Do I need API keys? =
Frankfurter (forex), CoinGecko (crypto), and the ECB work without a key. Alpha Vantage and Twelve Data need free keys from their sites, entered under Settings → NR Stocks.

= What are the free-tier limits? =
Alpha Vantage free allows 25 requests per day and returns the latest 100 points of a series. Twelve Data free allows 8 credits per minute and 800 per day, for internal/non-display use — public display needs a paid plan. The plugin's server-side cache keeps upstream calls low; pick a longer TTL on busy sites.

= How does caching work? =
Responses are cached per provider, endpoint, and parameters. Defaults are 5 minutes for time series, 1 minute for quotes, and 1 hour for symbol search; every value is adjustable, and high-traffic sites benefit from a persistent object cache (Redis/Memcached), which the plugin detects automatically.

= Can a non-WordPress frontend use the data? =
Yes. The `nr-stocks/v1` REST API exposes series, quote, search, and provider endpoints with cache headers and rate limits, returning the same normalized envelopes the block uses.

= Does the old Stock Information block still work? =
Posts using the 0.x `dj/stock-information` block keep their saved charts on the frontend. Re-insert them as the new Stock Chart block to get the editor preview back; your API token and line color migrate automatically.

== Screenshots ==

1. The Stock Chart block in the editor with symbol search and live preview.
2. Frontend chart with comparison overlay and quote panel.
3. Settings screen: providers, API keys, and cache TTLs.

== Changelog ==

= 1.0.1 =
* Fixed: the settings page can save again, and 0.x settings migrate on activation.
* Fixed: saved 0.x charts load on the frontend, and the quote panel and comparison legend appear.
* Fixed: CoinGecko, Frankfurter, and Alpha Vantage charts and symbol search return the right data.
* Fixed: failed refreshes serve the last good data and back off, and temporary failures are not stored by page caches.
* Changed: the settings page no longer prints saved API keys. Leave a field empty to keep its key.
* Changed: REST hardening: the cross-origin host list applies to plugin routes, `ttl` is for editors only, logged-out cache misses have a budget, and JSONP is off.
* Uninstall removes plugin data from every site on a multisite network.

= 1.0.0 =
* Complete rebuild as NR Stocks for the WordPress.org directory.
* New Stock Chart block (`nr-stocks/stock-chart`) with multi-symbol comparison, series picker, and quote panel.
* Provider registry: Alpha Vantage, Twelve Data, Frankfurter, CoinGecko, and ECB reference rates, all swappable per block.
* Server-side caching with variable TTLs, stampede protection, and purge controls.
* `[nr-stocks]` shortcode with server-rendered output and no-JavaScript fallback.
* Versioned REST API (`nr-stocks/v1`) for headless frontends.
* Breaking: the 0.x `dj/stock-information` block is retired; saved frontend output keeps rendering, settings migrate automatically.

== Upgrade Notice ==

= 1.0.1 =
Fixes settings saving, 0.x migration, and provider data, and hardens the REST API. REST clients should review the `ttl`, cross-origin, and JSONP changes in the changelog.

= 1.0.0 =
Complete rebuild. Re-insert 0.x blocks as the new Stock Chart block; your API token and chart color migrate automatically.

== External Services ==

This plugin contacts third-party market-data services from your server (never with visitor data — only the ticker symbol, series type, and your API key where required):

* Alpha Vantage — https://www.alphavantage.co/ — Terms: https://www.alphavantage.co/terms_of_service/ — Privacy: https://www.alphavantage.co/privacy/
* Twelve Data — https://twelvedata.com/ — Terms: https://twelvedata.com/terms — Privacy: https://twelvedata.com/privacy-policy
* Frankfurter — https://frankfurter.dev/ — free, keyless, open source
* CoinGecko — https://www.coingecko.com/ — Terms: https://www.coingecko.com/en/terms — Privacy: https://www.coingecko.com/en/privacy
* European Central Bank Data Portal — https://data-api.ecb.europa.eu/ — free, keyless, official reference rates

No tracking or analytics data is sent anywhere. API responses are cached on your server per the TTL settings.
