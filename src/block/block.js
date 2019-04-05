/**
 * BLOCK: stock-information
 *
 * Registering a basic block with Gutenberg.
 * Simple block, renders and saves the same content without any interactivity.
 */

//  Import CSS.
import './style.scss';
import './editor.scss';

import edit from './edit';
// import save from './save';

import SVGIcon from './SVGIcon';

const { __, setLocaleData } = wp.i18n; // Import __() from wp.i18n
const { registerBlockType } = wp.blocks; // Import registerBlockType() from wp.blocks
const {
  RichText,
} = wp.editor;
const { Fragment } = wp.element;

const {
  InnerBlocks,
} = wp.editor;
const {
  Tooltip
} = wp.components;

/**
 * Plugin Constants.
 */
const blockName = 'stock-information';
const blockNameSpace = `dj/${blockName}`;
const langDomain = 'dj-stock';

let config_data = JSON.stringify(stockinfo_config);
let content = JSON.parse(config_data);

console.log(content);

// Read config settings
const apiKey = content.token;
const host = content.host;
const entrypoint = content.entrypoint;

// Register the textdomain.
setLocaleData({ '': {} }, langDomain);

const stockIcon = <SVGIcon name="test" width={20} fill={'gray'}/>;

/**
 * Register: aa Gutenberg Block.
 *
 * Registers a new block provided a unique name and an object defining its
 * behavior. Once registered, the block is made editor as an option to any
 * editor interface where blocks are implemented.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType( blockNameSpace, {
  // Block name. Block names must be string that contains a namespace prefix. Example: my-plugin/my-custom-block.
  title: __('Stock Information', langDomain), // Block title.
  icon: stockIcon, // Block icon from Dashicons → https://developer.wordpress.org/resource/dashicons/.
  category: 'common', // Block category — Group blocks together based on common traits E.g. common, formatting, layout
                      // widgets, embed.
  keywords: [
    __('stock-information — DJ Block', langDomain),
    __('DJ Example', langDomain),
    __('create-guten-block', langDomain),
  ],
  attributes: {
    symbol: {
      type: 'string',
      default: '', // 'DJIA'
    },
    name: {
      type: 'string',
      default: '', // 'Dow Jones Industrial Average',
    },
    apiKey: {
      type: 'string',
      default: `${apiKey}`,
    },
  },
  supports: {
    customClassName: false,
    className: true,
    reusable: false,
    setAttributes: true,
    // alignWide: true,
    html: false,
    align: ['left', 'center', 'wide', 'full'],
  },
  /**
   * Update Alignment (placement).
   * @param blockAlignment
   * @returns {*}
   */
  getEditWrapperProps({ align }) {
    let alignVal = 'center';
    if ('left' === align ||
      'right' === align ||
      'center' === align ||
      'wide' === align ||
      'full' === align) {
      alignVal = align;
    }
  },
  edit,
  save: function(){
    return null;
  },
});
