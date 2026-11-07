/**
 * Frontend hydration: renders baked chart data, no refetch on load.
 *
 * Also hydrates legacy 0.x `.stock-information-item` markup through the REST
 * API so old posts keep working.
 */
import {
	renderChartSVG,
	renderLegendHTML,
	renderQuotesHTML,
} from '../../shared/chart';
import { fetchSeries, sanitizeSymbol } from '../../shared/query';

const renderFigure = ( figure ) => {
	// The block and the shortcode enqueue separate handles for this bundle.
	if ( figure.classList.contains( 'is-rendered' ) ) {
		return;
	}
	const script = figure.querySelector( 'script.nr-stocks-data' );
	if ( ! script ) {
		return;
	}
	const canvas = figure.querySelector( '.nr-stocks-canvas' );
	if ( ! canvas ) {
		return;
	}
	let payload = null;
	try {
		payload = JSON.parse( script.textContent );
	} catch {
		return;
	}
	if ( ! payload || ! payload.symbols ) {
		return;
	}
	const seriesList = Object.entries( payload.symbols )
		.filter(
			( [ , entry ] ) =>
				entry &&
				! entry.error &&
				Array.isArray( entry.rows ) &&
				entry.rows.length
		)
		.map( ( [ symbol, entry ] ) => ( {
			symbol,
			rows: entry.rows,
			quote: entry.quote,
		} ) );
	if ( ! seriesList.length ) {
		return;
	}
	canvas.innerHTML = renderChartSVG( seriesList, {
		color: payload.color,
		labels: payload.labels,
	} );
	canvas.insertAdjacentHTML(
		'afterend',
		renderLegendHTML( seriesList, { color: payload.color } ) +
			renderQuotesHTML( seriesList )
	);
	const fallback = figure.querySelector( '.nr-stocks-fallback' );
	if ( fallback ) {
		fallback.hidden = true;
	}
	canvas.closest( 'figure' )?.classList.add( 'is-rendered' );
};

const hydrateLegacy = async ( element, restBase ) => {
	const symbol = sanitizeSymbol( element.dataset.symbol || '' );
	if ( ! symbol || ! restBase || element.dataset.nrStocksHydrated ) {
		return;
	}
	element.dataset.nrStocksHydrated = '1';
	const result = await fetchSeries( restBase, { symbols: [ symbol ] } );
	const entry = result.ok && result.data ? result.data[ symbol ] : null;
	if ( ! entry || ! entry.ok || ! entry.data || ! entry.data.length ) {
		return;
	}
	element.innerHTML = renderChartSVG( [ { symbol, rows: entry.data } ], {} );
};

const ready = ( callback ) => {
	if ( document.readyState !== 'loading' ) {
		callback();
	} else {
		document.addEventListener( 'DOMContentLoaded', callback );
	}
};

ready( () => {
	document
		.querySelectorAll( 'figure.nr-stocks[data-nr-stocks]' )
		.forEach( renderFigure );

	// Legacy 0.x markup: the REST URL comes from the server when it loaded this
	// script for legacy charts, otherwise the pretty-permalink default.
	const legacy = document.querySelectorAll(
		'.stock-information-item[data-symbol]'
	);
	if ( legacy.length ) {
		const restBase =
			window.nrStocks?.restBase ||
			`${ window.location.origin }/wp-json/nr-stocks/v1`;
		legacy.forEach( ( element ) => hydrateLegacy( element, restBase ) );
	}
} );
