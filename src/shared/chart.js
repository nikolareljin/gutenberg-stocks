/**
 * Pure SVG chart builder shared by the editor preview and the frontend.
 *
 * Everything exported here is side-effect free and unit-tested; numbers are
 * validated finite before they reach the markup, and symbols are escaped.
 */
import { scaleLinear, scaleTime } from 'd3-scale';
import { area as d3area, curveMonotoneX, line as d3line } from 'd3-shape';
import { timeFormat, timeParse } from 'd3-time-format';

export const PALETTE = [
	'#0b163b',
	'#b3541e',
	'#1e7b34',
	'#6a1eb3',
	'#b31e5e',
];

const parseDateTime = timeParse( '%Y-%m-%d %H:%M:%S' );
const parseDate = timeParse( '%Y-%m-%d' );
const formatDay = timeFormat( '%b %d' );
const formatTime = timeFormat( '%H:%M' );

const isFiniteNumber = ( value ) =>
	typeof value === 'number' && Number.isFinite( value );

export const escapeXml = ( value ) =>
	String( value ).replace( /[&<>"']/g, ( char ) => {
		switch ( char ) {
			case '&':
				return '&amp;';
			case '<':
				return '&lt;';
			case '>':
				return '&gt;';
			case '"':
				return '&quot;';
			default:
				return '&#39;';
		}
	} );

export const toDate = ( value ) => {
	if ( value instanceof Date && ! Number.isNaN( value.getTime() ) ) {
		return value;
	}
	if ( typeof value !== 'string' ) {
		return null;
	}
	return parseDateTime( value ) || parseDate( value ) || null;
};

/**
 * Drops rows without a valid date or finite close.
 *
 * @param {Array} rows Raw rows with date/close.
 * @return {Array} Normalized rows with Date + numeric close.
 */
export function normalizeRows( rows ) {
	if ( ! Array.isArray( rows ) ) {
		return [];
	}
	const out = [];
	rows.forEach( ( row ) => {
		if ( ! row ) {
			return;
		}
		const date = toDate( row.date );
		const close = Number( row.close );
		if ( ! date || ! isFiniteNumber( close ) ) {
			return;
		}
		out.push( { date, close } );
	} );
	out.sort( ( a, b ) => a.date - b.date );
	return out;
}

/**
 * Re-bases closes to percent change from the first point.
 *
 * @param {Array} rows Normalized rows.
 * @return {Array} Rows with an added `value` field.
 */
export function toPercentChange( rows ) {
	if ( ! rows.length ) {
		return [];
	}
	const first = rows[ 0 ].close;
	if ( ! first ) {
		return rows.map( ( row ) => ( { ...row, value: 0 } ) );
	}
	return rows.map( ( row ) => ( {
		...row,
		value: ( ( row.close - first ) / Math.abs( first ) ) * 100,
	} ) );
}

/**
 * Builds scales, paths, and ticks for one or more series.
 *
 * @param {Array}  seriesList List of { symbol, rows }.
 * @param {Object} opts       { width, height, color, normalize }.
 * @return {Object} Chart model with SVG-ready geometry.
 */
export function buildChartModel( seriesList, opts = {} ) {
	const width = opts.width || 640;
	const height = opts.height || 360;
	const margin = { top: 12, right: 12, bottom: 28, left: 56 };
	const innerWidth = width - margin.left - margin.right;
	const innerHeight = height - margin.top - margin.bottom;

	const prepared = ( Array.isArray( seriesList ) ? seriesList : [] )
		.map( ( entry ) => ( {
			symbol: entry && entry.symbol ? String( entry.symbol ) : '',
			rows: normalizeRows( entry ? entry.rows : [] ),
		} ) )
		.filter( ( entry ) => entry.rows.length > 0 );

	const empty = {
		width,
		height,
		margin,
		paths: [],
		yTicks: [],
		xTicks: [],
		normalized: false,
	};
	if ( ! prepared.length ) {
		return empty;
	}

	const normalized =
		opts.normalize !== undefined ? !! opts.normalize : prepared.length > 1;
	const withValues = prepared.map( ( entry ) => ( {
		...entry,
		rows: normalized
			? toPercentChange( entry.rows )
			: entry.rows.map( ( row ) => ( { ...row, value: row.close } ) ),
	} ) );

	const allDates = withValues.flatMap( ( entry ) =>
		entry.rows.map( ( row ) => row.date )
	);
	const allValues = withValues.flatMap( ( entry ) =>
		entry.rows.map( ( row ) => row.value )
	);
	let min = Math.min( ...allValues );
	let max = Math.max( ...allValues );
	if ( min === max ) {
		const pad = Math.abs( min ) || 1;
		min -= pad * 0.05;
		max += pad * 0.05;
	} else {
		const span = max - min;
		min -= span * 0.05;
		max += span * 0.05;
	}

	const xScale = scaleTime()
		.domain( [
			new Date( Math.min( ...allDates ) ),
			new Date( Math.max( ...allDates ) ),
		] )
		.range( [ 0, innerWidth ] );
	const yScale = scaleLinear()
		.domain( [ min, max ] )
		.range( [ innerHeight, 0 ] );

	const line = d3line()
		.x( ( row ) => xScale( row.date ) )
		.y( ( row ) => yScale( row.value ) )
		.curve( curveMonotoneX );
	const area = d3area()
		.x( ( row ) => xScale( row.date ) )
		.y0( innerHeight )
		.y1( ( row ) => yScale( row.value ) )
		.curve( curveMonotoneX );

	const baseColor = opts.color || PALETTE[ 0 ];
	const paths = withValues.map( ( entry, index ) => ( {
		symbol: entry.symbol,
		color: index === 0 ? baseColor : PALETTE[ index % PALETTE.length ],
		d: line( entry.rows ) || '',
		area: index === 0 ? area( entry.rows ) || '' : '',
		last: entry.rows[ entry.rows.length - 1 ],
	} ) );

	const yTicks = [ 0, 1, 2, 3 ].map( ( step ) => {
		const value = min + ( ( max - min ) * step ) / 3;
		return {
			value,
			y: yScale( value ),
			label: normalized
				? `${ value >= 0 ? '+' : '' }${ value.toFixed( 2 ) }%`
				: value.toLocaleString( 'en-US', { maximumFractionDigits: 2 } ),
		};
	} );

	const spanMs = Math.max( ...allDates ) - Math.min( ...allDates );
	const intraday = spanMs < 3 * 86400000;
	const tickCount = Math.min( 6, prepared[ 0 ].rows.length );
	const xTicks = [];
	for ( let i = 0; i < tickCount; i++ ) {
		const row =
			prepared[ 0 ].rows[
				Math.floor(
					( i * ( prepared[ 0 ].rows.length - 1 ) ) /
						Math.max( 1, tickCount - 1 )
				)
			];
		xTicks.push( {
			x: xScale( row.date ),
			label: intraday ? formatTime( row.date ) : formatDay( row.date ),
		} );
	}

	return { width, height, margin, paths, yTicks, xTicks, normalized };
}

/**
 * Renders the chart model as an SVG string.
 *
 * @param {Array}  seriesList List of { symbol, rows }.
 * @param {Object} opts       Chart options.
 * @return {string} SVG markup.
 */
export function renderChartSVG( seriesList, opts = {} ) {
	const model = buildChartModel( seriesList, opts );
	const { width, height, margin } = model;
	if ( ! model.paths.length ) {
		return `<svg viewBox="0 0 ${ width } ${ height }" role="img" aria-label="No data"><text x="${ width / 2 }" y="${ height / 2 }" text-anchor="middle" fill="#888">No data</text></svg>`;
	}

	const grid = model.yTicks
		.map(
			( tick ) =>
				`<line x1="0" x2="${ width - margin.left - margin.right }" y1="${ tick.y }" y2="${ tick.y }" stroke="#e5e5e5" stroke-width="1"/><text x="-8" y="${ tick.y + 4 }" text-anchor="end" font-size="11" fill="#666">${ escapeXml( tick.label ) }</text>`
		)
		.join( '' );
	const xLabels = model.xTicks
		.map(
			( tick ) =>
				`<text x="${ tick.x }" y="${ height - margin.top - margin.bottom + 18 }" text-anchor="middle" font-size="11" fill="#666">${ escapeXml( tick.label ) }</text>`
		)
		.join( '' );
	const series = model.paths
		.map(
			( path, index ) =>
				`<g><title>${ escapeXml( path.symbol ) }</title>` +
				( path.area
					? `<path d="${ path.area }" fill="${ escapeXml( path.color ) }" fill-opacity="0.12" stroke="none"/>`
					: '' ) +
				`<path d="${ path.d }" fill="none" stroke="${ escapeXml( path.color ) }" stroke-width="${ index === 0 ? 2.5 : 2 }"/>` +
				`</g>`
		)
		.join( '' );
	return `<svg viewBox="0 0 ${ width } ${ height }" role="img" aria-label="Price chart"><g transform="translate(${ margin.left },${ margin.top })">${ grid }${ xLabels }${ series }</g></svg>`;
}

/**
 * Renders the chart into a DOM element.
 *
 * @param {Element} el         Container.
 * @param {Array}   seriesList List of { symbol, rows }.
 * @param {Object}  opts       Chart options.
 */
export function renderInto( el, seriesList, opts = {} ) {
	if ( ! el ) {
		return;
	}
	el.innerHTML = renderChartSVG( seriesList, opts );
}
