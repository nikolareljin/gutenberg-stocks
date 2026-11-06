import {
	buildRestPath,
	clearInflight,
	fetchJson,
	fetchSeries,
	sanitizeSymbol,
	searchSymbols,
} from './query';

describe( 'sanitizeSymbol', () => {
	it.each( [
		[ 'aapl ', 'AAPL' ],
		[ 'eur/usd', 'EUR/USD' ],
		[ 'BRK.B', 'BRK.B' ],
		[ '<b>AAPL</b>', 'AAPL' ],
		[ '$MSFT!', 'MSFT' ],
		[ '', '' ],
		[ null, '' ],
	] )( 'cleans %p to %p', ( input, expected ) => {
		expect( sanitizeSymbol( input ) ).toBe( expected );
	} );

	it( 'truncates to 32 chars', () => {
		expect( sanitizeSymbol( 'A'.repeat( 40 ) ) ).toHaveLength( 32 );
	} );
} );

describe( 'buildRestPath', () => {
	it( 'joins base, route, and encoded params', () => {
		expect(
			buildRestPath(
				'https://example.test/wp-json/nr-stocks/v1/',
				'series',
				{
					provider: 'twelvedata',
					symbols: [ 'AAPL', 'MSFT' ],
					range: 30,
					ttl: '',
				}
			)
		).toBe(
			'https://example.test/wp-json/nr-stocks/v1/series?provider=twelvedata&symbols=AAPL%2CMSFT&range=30'
		);
	} );

	it( 'omits empty params and the query string when empty', () => {
		expect(
			buildRestPath( '/wp-json/nr-stocks/v1', 'providers', {} )
		).toBe( '/wp-json/nr-stocks/v1/providers' );
	} );
} );

describe( 'fetchJson', () => {
	beforeEach( () => clearInflight() );

	it( 'dedupes concurrent identical requests', async () => {
		let calls = 0;
		const fetchImpl = jest.fn( async () => {
			calls += 1;
			return { ok: true, json: async () => ( { ok: true } ) };
		} );
		const [ first, second ] = await Promise.all( [
			fetchJson( 'https://x.test/a', fetchImpl ),
			fetchJson( 'https://x.test/a', fetchImpl ),
		] );
		expect( calls ).toBe( 1 );
		expect( first ).toEqual( { ok: true, data: { ok: true } } );
		expect( second ).toEqual( first );
	} );

	it( 'normalizes HTTP errors and network failures', async () => {
		const bad = await fetchJson( 'https://x.test/500', async () => ( {
			ok: false,
			status: 500,
		} ) );
		expect( bad ).toEqual( { ok: false, error: 'http_500' } );

		const down = await fetchJson( 'https://x.test/down', async () => {
			throw new Error( 'boom' );
		} );
		expect( down ).toEqual( { ok: false, error: 'network_error' } );
	} );
} );

describe( 'searchSymbols', () => {
	beforeEach( () => clearInflight() );

	it( 'short-circuits short queries', async () => {
		const fetchImpl = jest.fn();
		expect( await searchSymbols( '/base', '', 'a', fetchImpl ) ).toEqual( {
			ok: true,
			data: [],
		} );
		expect( fetchImpl ).not.toHaveBeenCalled();
	} );

	it( 'unwraps the data envelope', async () => {
		const fetchImpl = jest.fn( async () => ( {
			ok: true,
			json: async () => ( { data: [ { symbol: 'AAPL' } ] } ),
		} ) );
		const result = await searchSymbols(
			'/base',
			'twelvedata',
			'apple',
			fetchImpl
		);
		expect( result ).toEqual( { ok: true, data: [ { symbol: 'AAPL' } ] } );
		expect( fetchImpl.mock.calls[ 0 ][ 0 ] ).toContain( 'q=apple' );
	} );
} );

describe( 'fetchSeries', () => {
	beforeEach( () => clearInflight() );

	it( 'rejects empty symbols without fetching', async () => {
		const fetchImpl = jest.fn();
		expect(
			await fetchSeries( '/base', { symbols: [] }, fetchImpl )
		).toEqual( {
			ok: false,
			error: 'bad_symbol',
		} );
		expect( fetchImpl ).not.toHaveBeenCalled();
	} );

	it( 'sanitizes and caps symbols at five', async () => {
		const fetchImpl = jest.fn( async () => ( {
			ok: true,
			json: async () => ( { data: {} } ),
		} ) );
		await fetchSeries(
			'/base',
			{ symbols: [ 'aapl', 'msft', 'x', 'y', 'z', 'extra' ] },
			fetchImpl
		);
		const url = fetchImpl.mock.calls[ 0 ][ 0 ];
		expect( url ).toContain( 'symbols=AAPL%2CMSFT%2CX%2CY%2CZ' );
		expect( url ).not.toContain( 'EXTRA' );
	} );
} );
