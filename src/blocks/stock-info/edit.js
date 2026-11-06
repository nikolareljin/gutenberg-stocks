/**
 * Stock Chart block editor: symbol search, provider controls, live preview.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	PanelBody,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';

import './editor.scss';
import { renderChartSVG } from '../../shared/chart';
import { sanitizeSymbol } from '../../shared/query';

const SERIES_OPTIONS = [
	{ label: 'Daily', value: 'daily' },
	{ label: 'Weekly', value: 'weekly' },
	{ label: 'Monthly', value: 'monthly' },
	{ label: 'Intraday', value: 'intraday' },
];

const restPath = ( route, params = {} ) => {
	const query = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== '' && value !== null && value !== undefined ) {
			query.set( key, value );
		}
	} );
	const suffix = query.toString();
	return `/nr-stocks/v1/${ route }${ suffix ? `?${ suffix }` : '' }`;
};

function useDebouncedValue( value, delay ) {
	const [ debounced, setDebounced ] = useState( value );
	useEffect( () => {
		const timer = setTimeout( () => setDebounced( value ), delay );
		return () => clearTimeout( timer );
	}, [ value, delay ] );
	return debounced;
}

export default function Edit( { attributes, setAttributes } ) {
	const { symbols, provider, series, range, showQuote, cacheTtl } =
		attributes;
	const blockProps = useBlockProps( { className: 'nr-stocks-editor' } );

	const [ providers, setProviders ] = useState( [] );
	const [ query, setQuery ] = useState( '' );
	const [ suggestions, setSuggestions ] = useState( [] );
	const [ preview, setPreview ] = useState( null );
	const [ previewState, setPreviewState ] = useState( 'idle' );
	const requestId = useRef( 0 );
	const debouncedQuery = useDebouncedValue( query, 300 );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: restPath( 'providers' ) } )
			.then( ( res ) => {
				if ( ! cancelled && res && Array.isArray( res.data ) ) {
					setProviders( res.data );
				}
			} )
			.catch( () => {} );
		return () => {
			cancelled = true;
		};
	}, [] );

	useEffect( () => {
		if ( debouncedQuery.trim().length < 2 ) {
			setSuggestions( [] );
			return;
		}
		const id = ++requestId.current;
		apiFetch( {
			path: restPath( 'search', { provider, q: debouncedQuery.trim() } ),
		} )
			.then( ( res ) => {
				if (
					id === requestId.current &&
					res &&
					Array.isArray( res.data )
				) {
					setSuggestions( res.data.slice( 0, 8 ) );
				}
			} )
			.catch( () => {
				if ( id === requestId.current ) {
					setSuggestions( [] );
				}
			} );
	}, [ debouncedQuery, provider ] );

	useEffect( () => {
		if ( ! symbols.length ) {
			setPreview( null );
			setPreviewState( 'idle' );
			return;
		}
		const id = ++requestId.current;
		setPreviewState( 'loading' );
		apiFetch( {
			path: restPath( 'series', {
				provider,
				symbols: symbols.join( ',' ),
				series,
				range,
			} ),
		} )
			.then( ( res ) => {
				if ( id !== requestId.current ) {
					return;
				}
				if ( res && res.data ) {
					setPreview( res.data );
					setPreviewState( 'ready' );
				} else {
					setPreviewState( 'error' );
				}
			} )
			.catch( () => {
				if ( id === requestId.current ) {
					setPreviewState( 'error' );
				}
			} );
	}, [ symbols, provider, series, range ] );

	const addSymbol = ( raw ) => {
		const clean = sanitizeSymbol( raw );
		if ( ! clean || symbols.includes( clean ) || symbols.length >= 5 ) {
			return;
		}
		setAttributes( { symbols: [ ...symbols, clean ] } );
		setQuery( '' );
		setSuggestions( [] );
	};

	const removeSymbol = ( target ) => {
		setAttributes( { symbols: symbols.filter( ( s ) => s !== target ) } );
	};

	const activeProvider = providers.find( ( p ) => p.id === provider );
	const seriesOptions = SERIES_OPTIONS.filter( ( option ) => {
		if ( ! provider || ! activeProvider ) {
			return true;
		}
		return Object.prototype.hasOwnProperty.call(
			activeProvider.series || {},
			option.value
		);
	} );

	const previewSeries =
		previewState === 'ready' && preview
			? symbols
					.map( ( symbol ) => {
						const entry = preview[ symbol ];
						return entry && entry.ok
							? { symbol, rows: entry.data || [] }
							: null;
					} )
					.filter( Boolean )
			: [];

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Data Source', 'nr-stocks' ) }>
					<SelectControl
						label={ __( 'Provider', 'nr-stocks' ) }
						value={ provider }
						options={ [
							{
								label: __( 'Site default', 'nr-stocks' ),
								value: '',
							},
							...providers.map( ( p ) => ( {
								label:
									p.needs_key && ! p.has_key
										? `${ p.label } (needs key)`
										: p.label,
								value: p.id,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( { provider: value } )
						}
						help={ __(
							'Keys are configured under Settings → NR Stocks.',
							'nr-stocks'
						) }
					/>
					<SelectControl
						label={ __( 'Series', 'nr-stocks' ) }
						value={ series }
						options={ seriesOptions }
						onChange={ ( value ) =>
							setAttributes( { series: value } )
						}
					/>
					<RangeControl
						label={ __( 'Points to show', 'nr-stocks' ) }
						value={ range }
						min={ 5 }
						max={ 365 }
						onChange={ ( value ) =>
							setAttributes( { range: value || 30 } )
						}
					/>
					<ToggleControl
						label={ __( 'Show quote panel', 'nr-stocks' ) }
						checked={ !! showQuote }
						onChange={ ( value ) =>
							setAttributes( { showQuote: value } )
						}
					/>
					<TextControl
						label={ __(
							'Cache TTL (seconds, 0 = site default)',
							'nr-stocks'
						) }
						type="number"
						min={ 0 }
						max={ 86400 }
						value={ cacheTtl }
						onChange={ ( value ) =>
							setAttributes( {
								cacheTtl: Math.max(
									0,
									parseInt( value, 10 ) || 0
								),
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div className="nr-stocks-editor__search">
				<TextControl
					label={ __( 'Add symbol', 'nr-stocks' ) }
					placeholder={ __(
						'Type a company, pair, or coin…',
						'nr-stocks'
					) }
					value={ query }
					onChange={ setQuery }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' ) {
							event.preventDefault();
							addSymbol( query );
						}
					} }
				/>
				{ !! suggestions.length && (
					<ul className="nr-stocks-editor__suggestions">
						{ suggestions.map( ( option ) => (
							<li
								key={ `${ option.symbol }-${ option.exchange || '' }` }
							>
								<Button
									variant="tertiary"
									onClick={ () => addSymbol( option.symbol ) }
								>
									{ `${ option.symbol } — ${ option.name || '' }`.trim() }
								</Button>
							</li>
						) ) }
					</ul>
				) }
				{ !! symbols.length && (
					<ul className="nr-stocks-editor__chips">
						{ symbols.map( ( symbol ) => (
							<li key={ symbol }>
								<code>{ symbol }</code>{ ' ' }
								<Button
									variant="link"
									isDestructive
									onClick={ () => removeSymbol( symbol ) }
									label={ __( 'Remove symbol', 'nr-stocks' ) }
								>
									×
								</Button>
							</li>
						) ) }
					</ul>
				) }
			</div>

			<div className="nr-stocks-editor__preview">
				{ previewState === 'loading' && <Spinner /> }
				{ previewState === 'error' && (
					<p>
						{ __(
							'Preview unavailable. Check the provider key under Settings → NR Stocks.',
							'nr-stocks'
						) }
					</p>
				) }
				{ previewState === 'idle' && ! symbols.length && (
					<p>
						{ __(
							'Add at least one symbol to preview the chart.',
							'nr-stocks'
						) }
					</p>
				) }
				{ previewState === 'ready' && !! previewSeries.length && (
					<div
						className="nr-stocks-canvas"
						// Generated from numeric data only; no user HTML passes through.
						dangerouslySetInnerHTML={ {
							__html: renderChartSVG( previewSeries, {} ),
						} }
					/>
				) }
			</div>
		</div>
	);
}
