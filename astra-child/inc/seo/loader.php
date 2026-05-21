<?php
/**
 * SEO modules loader.
 *
 * Loads each SEO sub-module. Every module is wrapped in a filter so it can
 * be disabled from a site-specific plugin or wp-config.php without editing
 * the theme.
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map of module slug => default-enabled state.
 *
 * Override per-module by returning false from the matching filter:
 *   add_filter( 'astra_child_seo_module_performance', '__return_false' );
 *
 * @return array<string,bool>
 */
function astra_child_seo_modules() {
	return array(
		'performance'   => true,
		'arabic'        => true,
		'yoast_tweaks'  => true,
		'schema_extras' => true,
		'images'        => true,
		'robots'        => true,
		'critical_css'  => true,
	);
}

/**
 * Require each enabled module file.
 */
foreach ( astra_child_seo_modules() as $astra_child_seo_module => $astra_child_seo_default ) {
	/**
	 * Toggle a single SEO module on or off.
	 *
	 * Filter name pattern: astra_child_seo_module_{slug}.
	 *
	 * @param bool $enabled Whether the module should load.
	 */
	if ( ! apply_filters( "astra_child_seo_module_{$astra_child_seo_module}", $astra_child_seo_default ) ) {
		continue;
	}

	$astra_child_seo_file = __DIR__ . '/' . str_replace( '_', '-', $astra_child_seo_module ) . '.php';

	if ( is_readable( $astra_child_seo_file ) ) {
		require_once $astra_child_seo_file;
	}
}

unset( $astra_child_seo_module, $astra_child_seo_default, $astra_child_seo_file );
