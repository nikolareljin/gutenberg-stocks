/**
 * Stock Chart block registration (dynamic: rendered by PHP).
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	// Dynamic block: markup comes from the PHP render callback.
	save: () => null,
} );
