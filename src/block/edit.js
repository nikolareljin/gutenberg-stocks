/* global wp */

/**
 * BLOCK EDIT: DJ Stocks.
 *
 * Define Stocks block edit
 */
import classnames from 'classnames';
import PropTypes from 'prop-types';
import ChartComponent from './ChartComponent';

const {__, setLocaleData} = wp.i18n; // Import __() from wp.i18n
const {registerBlockType, InspectorControls} = wp.blocks; // Import registerBlockType() from wp.blocks
const {
	RichText,
} = wp.editor;
const {Fragment} = wp.element;
const {
	InnerBlocks
} = wp.editor;
const {
	Tooltip, PanelBody
} = wp.components;

/**
 * Plugin Constants.
 */
const blockName = 'stock-information';
const blockNameSpace = `dj/${blockName}`;
const langDomain = 'dj-stock';

let config_data = JSON.stringify(stockinfo_config);
let config_content = JSON.parse(config_data);

console.log('Content from EDIT');
console.log(config_content);

// Read config settings
const apiKey = config_content.token;
const host = config_content.host;
const entrypoint = config_content.entrypoint;

const completer = {
	name: 'companies',
	// The prefix that triggers this completer
	triggerPrefix: '$',
	// The option data
	options(search) {
		const srcUrl = host;
		const searchURL = srcUrl.concat(
			'/',
			entrypoint,
			'?',
			'function=SYMBOL_SEARCH',
			`&apikey=${apiKey}`,
			'&keywords=',
			search
		);
		console.log("URL for search: ");
		console.log(searchURL);

		return fetch(searchURL, {
			crossDomain: true,
			method: 'GET',
			headers: {'Content-Type': 'application/json'},
		})
			.then(response => response.json())
			.then(function (myJson) {
				const rawInput = myJson.bestMatches;
				const myArray = [];

				for (const x of rawInput) {
					myArray.push({name: x['2. name'], symbol: x['1. symbol'], currency: x['8. currency']});
				}
				console.log(myArray);

				return myArray;
			});
	},
	isDebounced: true,
	// Returns a label wrapper autocompleter list
	getOptionLabel: (option) => (
		<span>
      {option.symbol} ({option.name})
    </span>
	),
	// Declares that options should be matched by their name or ID
	getOptionKeywords: (option) => [option.name, option.symbol],
	// Declares completions should be inserted as URLs
	getOptionCompletion: (option) => (
		option.symbol
	),
};

const appendCompleter = (completers, blockNameEl) => {
	let value = null;
	if (completer) {
		if (blockNameSpace === blockNameEl) {
			value = completers.concat(completer);
			// Update the source, add symbol.
		} else {
			value = completers;
		}
	}
	return value;
};

// Adding the filter
wp.hooks.addFilter(
	'editor.Autocomplete.completers',
	blockNameSpace,
	appendCompleter
);

/**
 * Stocks Edit component.
 * @param props
 * @returns {*}
 */
const stocksEdit = (props) => {
	const {className, isSelected, attributes: {message, symbol, align, name}, setAttributes} = props;

	let symbol_val = symbol;

	const setupEditor = (editor) => {
		this.editor = editor;
	};

	// Placement
	/**
	 * Update layout after changing the Placement.
	 * @param value
	 */
	const onChangePlacement = (value) => {
		setAttributes(
			{
				align: value,
			}
		);
	};

	/**
	 * Inspector controls on the right side.
	 */
	const inspectorControls = (
		<InspectorControls key="stocksInspector">
			<PanelBody title={__('Front End Settings', langDomain)}>
				<p>To start entering new Stock Index, please type $ into the prompt field.</p>
			</PanelBody>
		</InspectorControls>
	);

	const onChangeMessage = (x) => {
		symbol_val = x;

		if (undefined != symbol_val) {
			symbol_val = symbol_val.replace('<p>', '');
			symbol_val = symbol_val.replace('</p>', '');
			symbol_val = symbol_val.replace('<br>', '');
		}

		x = x.replace('<p>', '');
		x = x.replace('</p>', '');
		x = x.replace('<br>', '');

		setAttributes({message: x});
		setAttributes({symbol: symbol_val});
	};

	if (undefined != symbol_val && !symbol_val.includes('$') && '' != symbol_val && undefined != apiKey && '' != apiKey) {

		return [
			isSelected,
			<Fragment key="stocksInfo">
				<div className={classnames(
					props.className,
					'wp-block-embed',
					`align${align}`,
					'placeholder-stock'
				)}
						 d
						 keepPlaceholderOnFocus={true}
				>
					<RichText
						onSetup={setupEditor}
						tagName="p"
						formattingControls={[]}
						placeholder={__('Enter Company name to find Stock Index.', langDomain)}
						onChange={onChangeMessage}
						value={message}
						keepPlaceholderOnFocus={true}
					/>
					<ChartComponent type={'hybrid'} symbol={symbol_val} apiKey={apiKey} name={name}/>
					<p>Stocks: <code>{symbol_val}</code>.
					</p>
				</div>
			</Fragment>
		];
	} else {
		return [
			isSelected,
			<Fragment key="stocksInfo">
				<div>
					<RichText
						onSetup={setupEditor}
						tagName="div"
						formattingControls={[]}
						placeholder={__('Enter Company name to find Stock Index.', langDomain)}
						onChange={onChangeMessage}
						value={message}
						keepPlaceholderOnFocus={true}
					/>
				</div>
			</Fragment>
		];
	}
};

stocksEdit.propTypes = {
	className: PropTypes.string,
	attributes: PropTypes.object.isRequired,
	setAttributes: PropTypes.func.isRequired,
};

stocksEdit.defaultProps = {
	className: '',
	attributes: {symbol: 'DJIA'}
};

export default stocksEdit;
