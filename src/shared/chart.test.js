import {
	PALETTE,
	buildChartModel,
	escapeXml,
	normalizeRows,
	renderChartSVG,
	renderLegendHTML,
	renderQuotesHTML,
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

describe( 'normalizeRows with missing prices', () => {
	it( 'drops null and empty closes instead of plotting zero', () => {
		const out = normalizeRows( [
			{ date: '2026-10-01', close: 5 },
			{ date: '2026-10-02', close: null },
			{ date: '2026-10-03', close: '' },
			{ date: '2026-10-04', close: 6 },
		] );
		expect( out.map( ( row ) => row.close ) ).toEqual( [ 5, 6 ] );
	} );
} );

describe( 'renderLegendHTML', () => {
	const rows = [
		{ date: '2026-10-01', close: 1 },
		{ date: '2026-10-02', close: 2 },
	];

	it( 'is empty for a single series', () => {
		expect( renderLegendHTML( [ { symbol: 'AAPL', rows } ] ) ).toBe( '' );
	} );

	it( 'matches chart colors when the chart drops a series', () => {
		const html = renderLegendHTML(
			[
				{ symbol: 'BAD', rows: [ { date: 'nope', close: 1 } ] },
				{ symbol: 'B', rows },
				{ symbol: 'C', rows },
			],
			{ color: '#123456' }
		);
		expect( html ).not.toContain( 'BAD' );
		expect( html ).toContain( 'background:#123456"></span>B</li>' );
		expect( html ).toContain(
			`background:${ PALETTE[ 1 ] }"></span>C</li>`
		);
	} );

	it( 'lists symbols with chart colors and escapes them', () => {
		const html = renderLegendHTML(
			[
				{ symbol: 'AAPL', rows },
				{ symbol: '<b>', rows },
			],
			{ color: '#123456' }
		);
		expect( html ).toContain( 'background:#123456' );
		expect( html ).toContain( `background:${ PALETTE[ 1 ] }` );
		expect( html ).toContain( '&lt;b&gt;' );
		expect( html ).not.toContain( '<b>' );
	} );
} );

describe( 'renderQuotesHTML', () => {
	it( 'renders price, currency, and signed change', () => {
		const html = renderQuotesHTML( [
			{
				symbol: 'IBM',
				quote: { price: 142.2, change_percent: 1.9, currency: 'USD' },
			},
			{
				symbol: 'MSFT',
				quote: { price: 10, change_percent: -0.5, currency: '' },
			},
		] );
		expect( html ).toContain( '142.2 USD' );
		expect( html ).toContain( '+1.90%' );
		expect( html ).toContain( 'is-up' );
		expect( html ).toContain( '-0.50%' );
		expect( html ).toContain( 'is-down' );
	} );

	it( 'skips missing quotes and omits an unknown change', () => {
		expect( renderQuotesHTML( [ { symbol: 'A', quote: null } ] ) ).toBe(
			''
		);
		expect(
			renderQuotesHTML( [ { symbol: 'A', quote: { price: null } } ] )
		).toBe( '' );
		const html = renderQuotesHTML( [
			{ symbol: 'A', quote: { price: 1, change_percent: null } },
		] );
		expect( html ).not.toContain( '%' );
	} );

	it( 'shows the date of the price', () => {
		const html = renderQuotesHTML( [
			{
				symbol: 'A',
				quote: { price: 1, date: '2022-03-01 10:00:00' },
			},
		] );
		expect( html ).toContain( '>2022-03-01<' );
	} );

	it( 'escapes upstream text', () => {
		const html = renderQuotesHTML( [
			{
				symbol: 'A',
				quote: { price: 1, currency: '<img src=x>' },
			},
		] );
		expect( html ).toContain( '&lt;img' );
		expect( html ).not.toContain( '<img' );
	} );
} );

describe( 'renderChartSVG x axis', () => {
	it( 'keeps the last label inside the chart', () => {
		const rows = [
			{ date: '2026-10-01', close: 1 },
			{ date: '2026-10-15', close: 2 },
			{ date: '2026-10-30', close: 3 },
		];
		const html = renderChartSVG( [ { symbol: 'A', rows } ] );
		const anchors = [
			...html.matchAll( /text-anchor="(\w+)"[^>]*fill="#666">Oct/g ),
		].map( ( match ) => match[ 1 ] );
		expect( anchors ).toEqual( [ 'middle', 'middle', 'end' ] );
	} );
} );

describe( 'renderChartSVG labels', () => {
	it( 'uses translated labels and escapes them', () => {
		const html = renderChartSVG( [], {
			labels: { noData: 'Sin "datos"' },
		} );
		expect( html ).toContain( 'Sin &quot;datos&quot;' );
		expect( html ).not.toContain( 'No data' );
	} );
} );
