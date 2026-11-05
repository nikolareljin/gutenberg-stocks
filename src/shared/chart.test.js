import {
	buildChartModel,
	escapeXml,
	normalizeRows,
	renderChartSVG,
	toDate,
	toPercentChange,
} from './chart';

describe( 'toDate', () => {
	it( 'parses date and datetime strings', () => {
		expect( toDate( '2026-10-01' ) ).toBeInstanceOf( Date );
		expect( toDate( '2026-10-01 15:30:00' ) ).toBeInstanceOf( Date );
	} );

	it.each( [ '', null, undefined, 'not-a-date', 42, {}, [] ] )(
		'rejects invalid input %p',
		( value ) => {
			expect( toDate( value ) ).toBeNull();
		}
	);
} );

describe( 'normalizeRows', () => {
	it( 'keeps valid rows sorted oldest-first', () => {
		const rows = normalizeRows( [
			{ date: '2026-10-03', close: '3' },
			{ date: '2026-10-01', close: 1 },
			{ date: '2026-10-02', close: 2 },
		] );
		expect( rows.map( ( row ) => row.close ) ).toEqual( [ 1, 2, 3 ] );
		expect( rows[ 0 ].date ).toBeInstanceOf( Date );
	} );

	it( 'drops rows without a date or finite close', () => {
		const rows = normalizeRows( [
			{ date: '2026-10-01', close: 10 },
			{ date: '', close: 10 },
			{ date: '2026-10-02', close: 'NaN' },
			{ date: '2026-10-03', close: Infinity },
			null,
		] );
		expect( rows ).toHaveLength( 1 );
	} );

	it( 'returns an empty array for non-array input', () => {
		expect( normalizeRows( null ) ).toEqual( [] );
		expect( normalizeRows( 'x' ) ).toEqual( [] );
	} );
} );

describe( 'toPercentChange', () => {
	it( 're-bases to the first close', () => {
		const rows = toPercentChange( [
			{ date: new Date(), close: 100 },
			{ date: new Date(), close: 110 },
		] );
		expect( rows[ 0 ].value ).toBe( 0 );
		expect( rows[ 1 ].value ).toBeCloseTo( 10 );
	} );

	it( 'handles a zero first close without NaN', () => {
		const rows = toPercentChange( [
			{ date: new Date(), close: 0 },
			{ date: new Date(), close: 5 },
		] );
		expect( rows.every( ( row ) => Number.isFinite( row.value ) ) ).toBe(
			true
		);
	} );
} );

describe( 'escapeXml', () => {
	it( 'escapes markup characters', () => {
		expect( escapeXml( '<A&"B\'>' ) ).toBe( '&lt;A&amp;&quot;B&#39;&gt;' );
	} );
} );

describe( 'renderChartSVG', () => {
	const rows = ( closes ) =>
		closes.map( ( close, index ) => ( {
			date: `2026-10-${ String( index + 1 ).padStart( 2, '0' ) }`,
			close,
		} ) );

	it( 'renders an empty-state svg without data', () => {
		const svg = renderChartSVG( [] );
		expect( svg ).toContain( '<svg' );
		expect( svg ).toContain( 'No data' );
	} );

	it( 'renders finite geometry for a single series', () => {
		const svg = renderChartSVG( [
			{ symbol: 'AAPL', rows: rows( [ 1, 2, 3 ] ) },
		] );
		expect( svg ).toContain( '<path' );
		expect( svg ).not.toMatch( /NaN|Infinity|undefined/ );
	} );

	it( 'normalizes multi-symbol overlays to percent ticks', () => {
		const model = buildChartModel(
			[
				{ symbol: 'A', rows: rows( [ 100, 110 ] ) },
				{ symbol: 'B', rows: rows( [ 5, 6 ] ) },
			],
			{}
		);
		expect( model.normalized ).toBe( true );
		expect( model.paths ).toHaveLength( 2 );
		const svg = renderChartSVG(
			[
				{ symbol: 'A', rows: rows( [ 100, 110 ] ) },
				{ symbol: 'B', rows: rows( [ 5, 6 ] ) },
			],
			{}
		);
		expect( svg ).toContain( '%' );
		expect( svg ).not.toMatch( /NaN|Infinity|undefined/ );
	} );

	it( 'escapes hostile symbols', () => {
		const svg = renderChartSVG( [
			{ symbol: '"><script>alert(1)</script>', rows: rows( [ 1, 2 ] ) },
		] );
		expect( svg ).not.toContain( '<script>' );
		expect( svg ).toContain( '&lt;script&gt;' );
	} );

	it( 'handles flat data without collapsing the scale', () => {
		const svg = renderChartSVG( [
			{ symbol: 'F', rows: rows( [ 5, 5, 5 ] ) },
		] );
		expect( svg ).not.toMatch( /NaN|Infinity|undefined/ );
	} );
} );
