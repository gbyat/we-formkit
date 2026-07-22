const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin-form-settings': './src/admin-form-settings/index.jsx',
	},
};
