<?php
/**
 * Strip cache-buster query strings from local CSS/JS URLs.
 *
 * Many edge caches and HTTP/2 push implementations refuse to cache URLs
 * that carry a query string, even when the underlying file is static.
 * Removing the `?ver=...` parameters that WordPress, Astra, and most
 * plugins append makes the asset URLs identical for every visitor and
 * fully cacheable on every CDN / proxy layer.
 *
 * Behavior:
 *
 *   - Only filters style_loader_src and script_loader_src on the front
 *     end. Admin, REST, AJAX, feeds, AMP, and the Customizer preview
 *     are left untouched - the Customizer in particular needs the
 *     version query string to bust its iframe cache while editing.
 *
 *   - Only strips the version off URLs that point to the current
 *     site's host. Third-party CDN URLs (e.g. fonts.googleapis.com)
 *     keep their original query string in case the provider relies
 *     on it for routing.
 *
 *   - Removes `ver`, `v`, `rev`, `cb`, `ts`, `_` query params - the
 *     conventional names used as cache-busters. Other unrelated
 *     query parameters (e.g. signed CDN tokens) are preserved.
 *
 * Trade-off: with the `?ver=` gone, a cached browser will not pick up
 * new CSS/JS until its own cache TTL expires or the file is renamed.
 * If you ship CSS/JS changes frequently, pair this module with the
 * filemtime-based versioning that wp_register_style/wp_register_script
 * use when given an absolute path. Most caching plugins also offer a
 * "purge cache" button that solves this in one click.
 *
 * @package Astra Child
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decide whether the current request should have query strings stripped.
 *
 * @return bool
 */
function astra_child_seo_qs_should_run() {
	if ( is_admin() || is_feed() || wp_doing_ajax() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		return false;
	}

	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return false;
	}

	/**
	 * Master toggle for the query-strings stripper.
	 *
	 * @param bool $enabled
	 */
	return (bool) apply_filters( 'astra_child_seo_remove_query_strings_enabled', true );
}

/**
 * Test whether a URL points to the current site (same host).
 *
 * Protocol-relative URLs (//example.com/file.js) and root-relative URLs
 * (/wp-content/...) are treated as same-origin.
 *
 * @param string $url Full or partial URL.
 * @return bool
 */
function astra_child_seo_qs_is_local( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return true; // path-only, assume local
	}

	$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( ! $home_host ) {
		return false;
	}

	return strcasecmp( $host, $home_host ) === 0;
}

/**
 * Default list of cache-buster query parameter names to strip.
 *
 * @return array<int,string>
 */
function astra_child_seo_qs_param_names() {
	$defaults = array( 'ver', 'v', 'rev', 'cb', 'ts', '_' );

	/**
	 * Filter the list of query parameters considered cache-busters.
	 *
	 * @param array<int,string> $params
	 */
	return (array) apply_filters( 'astra_child_seo_qs_param_names', $defaults );
}

/**
 * Strip cache-buster query strings from a single asset URL.
 *
 * @param string $src Original asset URL.
 * @return string
 */
function astra_child_seo_qs_filter_src( $src ) {
	if ( ! is_string( $src ) || '' === $src ) {
		return $src;
	}

	if ( ! astra_child_seo_qs_should_run() ) {
		return $src;
	}

	if ( false === strpos( $src, '?' ) ) {
		return $src;
	}

	if ( ! astra_child_seo_qs_is_local( $src ) ) {
		return $src;
	}

	/**
	 * Per-URL opt-out hook. Return false to skip stripping a specific
	 * URL (e.g. a query-string-driven configurator script you can't
	 * version any other way).
	 *
	 * @param bool   $should_strip Default true.
	 * @param string $src          The URL being processed.
	 */
	if ( ! apply_filters( 'astra_child_seo_remove_query_strings', true, $src ) ) {
		return $src;
	}

	$params  = astra_child_seo_qs_param_names();
	$stripped = remove_query_arg( $params, $src );

	// remove_query_arg leaves a trailing "?" when no params remain - clean it up.
	$stripped = rtrim( (string) $stripped, '?' );

	return $stripped;
}
add_filter( 'style_loader_src', 'astra_child_seo_qs_filter_src', 999 );
add_filter( 'script_loader_src', 'astra_child_seo_qs_filter_src', 999 );
