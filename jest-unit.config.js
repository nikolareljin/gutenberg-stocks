// Jest override picked up automatically by `wp-scripts test-unit-js`
// (see getJestOverrideConfigFile). Extends the WordPress preset with ESM
// transforms for the d3 modules, which ship ES Modules only.
const path = require( 'path' );
const baseConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...baseConfig,
	transform: {
		'\\.[jt]sx?$': path.join(
			__dirname,
			'node_modules/@wordpress/scripts/config/babel-transform'
		),
		'\\.mjs$': path.join(
			__dirname,
			'node_modules/@wordpress/scripts/config/babel-transform'
		),
	},
	transformIgnorePatterns: [
		'/node_modules/(?!(d3-.*|internmap|delaunator|robust-predicates)/)',
	],
	testPathIgnorePatterns: [
		'/node_modules/',
		'/\\.build/',
		'/dist/',
		'/build/',
	],
};
