/**
 * Stock Chart block registration (dynamic: rendered by PHP).
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';

// The editor reads a string icon as a Dashicon name, so the SVG string in
// block.json would render nothing. Pass the same drawing as an element.
const icon = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		<rect width="24" height="24" rx="5" fill="#0b163b" />
		<path
			d="M5 16 L9 12.5 L11.5 13.8 L15.5 8.5 L19 10"
			stroke="#6fa4fc"
			strokeWidth="1.8"
			strokeLinecap="round"
			strokeLinejoin="round"
			fill="none"
		/>
		<circle cx="19" cy="10" r="1.6" fill="#fff" />
	</svg>
);

registerBlockType( metadata.name, {
	...metadata,
	icon,
	edit: Edit,
	// Dynamic block: markup comes from the PHP render callback.
	save: () => null,
} );
