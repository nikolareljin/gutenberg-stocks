/**
 * REST client helpers for the nr-stocks/v1 API.
 *
 * The fetcher is injectable so unit tests run offline; production passes the
 * global fetch (frontend) or relies on apiFetch in the editor.
 */

const inflight = new Map();

/**
 * Mirrors the PHP symbol cleaner: uppercase, tag-stripped, allowlisted.
 *
 * @param {string} value Raw input.
 * @return {string} Clean symbol (max 32 chars).
 */
export function sanitizeSymbol( value ) {
	const cleaned = String( value || '' )
		.toUpperCase()
		.replace( /<[^>]*>/g, '' )
		.replace( /[^A-Z0-9.\-/=]/g, '' )
		.slice( 0, 32 );
	return cleaned;
}

/**
 * Joins a REST base with a route and query params (empties skipped).
 *
 * @param {string} base   Base URL or path.
 * @param {string} route  Route name.
 * @param {Object} params Query params.
 * @return {string} Full URL.
 */
export function buildRestPath( base, route, params = {} ) {
	const cleanBase = String( base || '' ).replace( /\/$/, '' );
	const query = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== '' && value !== null && value !== undefined ) {
			query.set(
				key,
				Array.isArray( value ) ? value.join( ',' ) : value
			);
		}
	} );
	const suffix = query.toString();
	// Plain permalinks give a base like /index.php?rest_route=/nr-stocks/v1.
	const separator = cleanBase.includes( '?' ) ? '&' : '?';
	return `${ cleanBase }/${ route }${ suffix ? `${ separator }${ suffix }` : '' }`;
}

/**
 * Fetches JSON with in-memory dedupe of concurrent identical requests.
 *
 * @param {string}   url       Request URL.
 * @param {Function} fetchImpl Fetch implementation.
 * @return {Promise<Object>} { ok, data } or { ok:false, error }.
 */
export function fetchJson( url, fetchImpl = globalThis.fetch ) {
	if ( inflight.has( url ) ) {
		return inflight.get( url );
	}
	const pending = ( async () => {
		try {
			const response = await fetchImpl( url, {
				headers: { Accept: 'application/json' },
			} );
			if ( ! response || response.ok === false ) {
				return {
					ok: false,
					error: `http_${ response ? response.status : 0 }`,
				};
			}
			const data = await response.json();
			return { ok: true, data };
		} catch {
			return { ok: false, error: 'network_error' };
		} finally {
			inflight.delete( url );
		}
	} )();
	inflight.set( url, pending );
	return pending;
}

/**
 * Clears the in-flight dedupe map (tests).
 */
export function clearInflight() {
	inflight.clear();
}

/**
 * Searches symbols through the proxy (keys stay server-side).
 *
 * @param {string}   restBase  REST base URL.
 * @param {string}   provider  Provider id.
 * @param {string}   query     Search text.
 * @param {Function} fetchImpl Fetch implementation.
 */
export async function searchSymbols( restBase, provider, query, fetchImpl ) {
	const clean = String( query || '' ).trim();
	if ( clean.length < 2 ) {
		return { ok: true, data: [] };
	}
	const url = buildRestPath( restBase, 'search', { provider, q: clean } );
	const result = await fetchJson( url, fetchImpl );
	if ( ! result.ok ) {
		return result;
	}
	const data =
		result.data && Array.isArray( result.data.data )
			? result.data.data
			: [];
	return { ok: true, data };
}

/**
 * Fetches series for one or more symbols.
 *
 * @param {string}   restBase  REST base URL.
 * @param {Object}   args      Request args.
 * @param {Function} fetchImpl Fetch implementation.
 */
export async function fetchSeries( restBase, args = {}, fetchImpl ) {
	const symbols = (
		Array.isArray( args.symbols ) ? args.symbols : [ args.symbols ]
	)
		.map( sanitizeSymbol )
		.filter( Boolean )
		.slice( 0, 5 );
	if ( ! symbols.length ) {
		return { ok: false, error: 'bad_symbol' };
	}
	const url = buildRestPath( restBase, 'series', {
		provider: args.provider || '',
		symbols,
		series: args.series || 'daily',
		range: args.range || 30,
		ttl: args.ttl || '',
	} );
	const result = await fetchJson( url, fetchImpl );
	if ( ! result.ok ) {
		return result;
	}
	return { ok: true, data: result.data ? result.data.data : {} };
}
