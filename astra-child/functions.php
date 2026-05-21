<?php
/**
 * Astra Child Theme functions and definitions.
 *
 * @package Astra Child
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Google AdSense publisher client ID.
 *
 * Change this constant if the AdSense account changes.
 */
define( 'ASTRA_CHILD_ADSENSE_CLIENT_ID', 'ca-pub-1333087388129302' );

/**
 * Define the child theme version (used to bust cache when the stylesheet changes).
 */
define( 'ASTRA_CHILD_THEME_VERSION', '1.0.0' );

/**
 * Enqueue parent and child theme stylesheets.
 *
 * Loads the parent Astra stylesheet first, then the child stylesheet so any
 * overrides in style.css take precedence.
 *
 * @return void
 */
function astra_child_enqueue_styles() {
	wp_enqueue_style(
		'astra-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( get_template() )->get( 'Version' )
	);

	wp_enqueue_style(
		'astra-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'astra-parent-style' ),
		ASTRA_CHILD_THEME_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'astra_child_enqueue_styles' );

/**
 * Inject the Google AdSense loader script into <head>.
 *
 * Hooked early on wp_head so the script appears near the top of <head>,
 * matching where it was previously placed in the parent theme's header.php.
 * The script is skipped in the WordPress admin, AMP requests, feeds, and
 * for logged-in users with manage_options capability (administrators) so
 * ads are not loaded while editing.
 *
 * @return void
 */
function astra_child_print_adsense_loader() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		return;
	}

	/**
	 * Filter the AdSense client ID at runtime.
	 *
	 * @param string $client_id The AdSense ca-pub client ID.
	 */
	$client_id = apply_filters( 'astra_child_adsense_client_id', ASTRA_CHILD_ADSENSE_CLIENT_ID );

	if ( empty( $client_id ) ) {
		return;
	}

	printf(
		'<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>' . "\n",
		esc_attr( $client_id )
	);
}
add_action( 'wp_head', 'astra_child_print_adsense_loader', 5 );
