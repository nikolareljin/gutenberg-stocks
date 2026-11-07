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

	it( 'keeps digits on very small prices', () => {
		const html = renderQuotesHTML( [
			{ symbol: 'A', quote: { price: 0.0000123 } },
		] );
		expect( html ).toContain( '0.0000123' );
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

describe( 'buildChartModel y axis', () => {
	it( 'gives neighboring ticks distinct labels on a narrow range', () => {
		const { yTicks } = buildChartModel( [
			{
				symbol: 'EUR/USD',
				rows: [
					{ date: '2026-10-01', close: 1.1124 },
					{ date: '2026-10-02', close: 1.1163 },
				],
			},
		] );
		const labels = yTicks.map( ( tick ) => tick.label );
		expect( new Set( labels ).size ).toBe( labels.length );
	} );

	it.each( [
		[ 0.0000123, 0.0000142 ],
		[ 0.00000121, 0.00000126 ],
		[ 1.21e-9, 1.26e-9 ],
	] )( 'keeps tiny prices short and distinct: %d', ( low, high ) => {
		const { yTicks } = buildChartModel( [
			{
				symbol: 'SHIB/USD',
				rows: [
					{ date: '2026-10-01', close: low },
					{ date: '2026-10-02', close: high },
				],
			},
		] );
		const labels = yTicks.map( ( tick ) => tick.label );
		expect( new Set( labels ).size ).toBe( labels.length );
		labels.forEach( ( label ) =>
			expect( label.length ).toBeLessThanOrEqual( 9 )
		);
	} );

	it.each( [
		[ 60000, 80000 ],
		[ 100000, 140000 ],
		[ 620000, 740000 ],
		[ 13000000, 15000000 ],
		[ 2000000000, 3000000000 ],
		[ 1700000000, 1720000000 ],
		[ 1705000000, 1712000000 ],
		[ 16300, 16302 ],
		[ 1350.12, 1350.13 ],
		[ 100000.1, 100000.4 ],
		[ 1.1124, 1.1163 ],
	] )( 'keeps large prices short and distinct: %d', ( low, high ) => {
		const { yTicks } = buildChartModel( [
			{
				symbol: 'BTC/USD',
				rows: [
					{ date: '2026-10-01', close: low },
					{ date: '2026-10-02', close: high },
				],
			},
		] );
		const labels = yTicks.map( ( tick ) => tick.label );
		expect( new Set( labels ).size ).toBe( labels.length );
		labels.forEach( ( label ) =>
			expect( label.length ).toBeLessThanOrEqual( 9 )
		);
	} );
} );

describe( 'buildChartModel y axis accuracy', () => {
	const parseLabel = ( label ) => {
		const match = label.replace( /,/g, '' ).match( /^(.*?)([KMBT]?)$/ );
		const scale = { '': 1, K: 1e3, M: 1e6, B: 1e9, T: 1e12 }[ match[ 2 ] ];
		return Number( match[ 1 ] ) * scale;
	};

	it.each( [
		[ 148.3, 151.9 ],
		[ 149.2, 152.8 ],
		[ 100.6, 102.4 ],
		[ 20, 320 ],
		[ 0.0000123, 0.0000142 ],
		[ 1.1124, 1.1163 ],
		[ 16300, 16302 ],
		[ 60000, 80000 ],
		[ 100000.1, 100000.4 ],
		[ 13000000, 15000000 ],
		[ 1705000000, 1712000000 ],
		[ 1.21e-9, 1.26e-9 ],
	] )(
		'labels stay within a tenth of a tick step: %d to %d',
		( low, high ) => {
			const { yTicks } = buildChartModel( [
				{
					symbol: 'A',
					rows: [
						{ date: '2026-10-01', close: low },
						{ date: '2026-10-02', close: high },
					],
				},
			] );
			const step = yTicks[ 1 ].value - yTicks[ 0 ].value;
			yTicks.forEach( ( tick ) => {
				expect(
					Math.abs( parseLabel( tick.label ) - tick.value )
				).toBeLessThanOrEqual( step * 0.1 * 1.0001 );
			} );
		}
	);
} );

describe( 'buildChartModel left margin', () => {
	const model = ( low, high, extra = {} ) =>
		buildChartModel(
			[
				{
					symbol: 'A',
					rows: [
						{ date: '2026-10-01', close: low },
						{ date: '2026-10-02', close: high },
					],
				},
			],
			extra
		);

	it( 'keeps the default margin for short labels', () => {
		expect( model( 148.3, 151.9 ).margin.left ).toBe( 56 );
	} );

	it.each( [
		[ 0.0000123, 0.0000124 ],
		[ 1.21e-9, 1.26e-9 ],
		[ 100000.1, 100000.4 ],
	] )( 'fits long price labels: %d to %d', ( low, high ) => {
		const { margin, yTicks } = model( low, high );
		const longest = Math.max( ...yTicks.map( ( t ) => t.label.length ) );
		expect( margin.left ).toBeGreaterThanOrEqual( longest * 7 + 12 - 8 );
	} );

	it( 'fits comparison labels above 10,000%', () => {
		const chart = buildChartModel( [
			{
				symbol: 'A',
				rows: [
					{ date: '2026-10-01', close: 1 },
					{ date: '2026-10-02', close: 250 },
				],
			},
			{
				symbol: 'B',
				rows: [
					{ date: '2026-10-01', close: 1 },
					{ date: '2026-10-02', close: 2 },
				],
			},
		] );
		expect( chart.yTicks[ 3 ].label ).toBe( '+26145.00%' );
		expect( chart.margin.left ).toBeGreaterThan( 56 );
	} );

	it( 'never takes more than a third of the width', () => {
		expect(
			model( 1.21e-9, 1.26e-9, { width: 120 } ).margin.left
		).toBeLessThanOrEqual( 40 );
	} );

	it( 'draws the plot inside the widened margin', () => {
		const chart = model( 0.0000123, 0.0000124 );
		const xs = chart.paths[ 0 ].d.match( /[ML]([-\d.]+)/g ) || [];
		const maxX = Math.max( ...xs.map( ( m ) => Number( m.slice( 1 ) ) ) );
		expect( maxX ).toBeCloseTo(
			chart.width - chart.margin.left - chart.margin.right,
			0
		);
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
