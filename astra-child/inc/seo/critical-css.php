<?php
/**
 * Critical CSS inliner.
 *
 * Inlines a small "above the fold" stylesheet directly in <head> so the
 * browser can paint immediately, then defers the full Astra/plugin CSS via
 * the media="print" onload swap (with a <noscript> fallback for accessibility).
 *
 * The result: better LCP and First Contentful Paint scores in PageSpeed Insights,
 * with no FOUC for users who have JavaScript enabled.
 *
 * Critical CSS files live in:
 *   astra-child/assets/critical/{template}.css
 *
 * Where {template} is one of: home, single, page, archive, default.
 * The module falls back to default.css when a template-specific file is missing.
 *
 * Disable everything:
 *   add_filter( 'astra_child_seo_critical_css_enabled', '__return_false' );
 *
 * Keep a stylesheet handle blocking (don't defer it):
 *   add_filter( 'astra_child_seo_critical_css_handles', function ( $handles ) {
 *       $handles[] = 'wp-block-library';
 *       return $handles;
 *   } );
 *
 * @package Astra Child
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decide whether the critical CSS optimization should run for the current request.
 *
 * Skips admin, feeds, AMP, customizer preview, and logged-in editors so they
 * always see the full stylesheet immediately.
 *
 * @return bool
 */
function astra_child_seo_critical_css_should_run() {
	if ( is_admin() || is_feed() ) {
		return false;
	}

	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		return false;
	}

	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return false;
	}

	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return false;
	}

	/**
	 * Filter to enable or disable the critical CSS feature globally.
	 *
	 * @param bool $enabled Default true.
	 */
	return (bool) apply_filters( 'astra_child_seo_critical_css_enabled', true );
}

/**
 * Resolve the template slug for the current request.
 *
 * @return string One of: home, single, page, archive, default.
 */
function astra_child_seo_critical_css_template() {
	if ( is_front_page() || is_home() ) {
		$template = 'home';
	} elseif ( is_singular( 'page' ) ) {
		$template = 'page';
	} elseif ( is_singular() ) {
		$template = 'single';
	} elseif ( is_archive() || is_search() ) {
		$template = 'archive';
	} else {
		$template = 'default';
	}

	/**
	 * Filter the template slug used to look up the critical CSS file.
	 *
	 * @param string $template Template slug.
	 */
	return (string) apply_filters( 'astra_child_seo_critical_css_template', $template );
}

/**
 * Resolve the absolute path to the critical CSS file for the current request.
 *
 * @return string Absolute path, may not exist.
 */
function astra_child_seo_critical_css_file() {
	$template = astra_child_seo_critical_css_template();
	$dir      = trailingslashit( get_stylesheet_directory() ) . 'assets/critical/';

	$path = $dir . $template . '.css';

	if ( ! is_readable( $path ) ) {
		$path = $dir . 'default.css';
	}

	/**
	 * Filter the resolved critical CSS file path.
	 *
	 * @param string $path     Absolute path to the file.
	 * @param string $template Template slug.
	 */
	return (string) apply_filters( 'astra_child_seo_critical_css_path', $path, $template );
}

/**
 * Inline the critical CSS at the very top of <head>.
 */
function astra_child_seo_inline_critical_css() {
	if ( ! astra_child_seo_critical_css_should_run() ) {
		return;
	}

	$file = astra_child_seo_critical_css_file();

	if ( ! is_readable( $file ) ) {
		return;
	}

	$css = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	if ( false === $css || '' === trim( $css ) ) {
		return;
	}

	$version = (int) filemtime( $file );

	printf(
		'<style id="ac-critical-css" data-version="%d">%s</style>' . "\n",
		$version,
		$css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file is theme-controlled CSS.
	);
}
add_action( 'wp_head', 'astra_child_seo_inline_critical_css', 1 );

/**
 * Defer non-critical stylesheets using the media="print" onload swap.
 *
 * Browsers download the stylesheet without blocking render (because media
 * is "print"), then JavaScript flips the media back to "all" once it loads.
 * A <noscript> tag carrying the original link is appended so users without
 * JS still get the full styles.
 *
 * @param string $tag    Original <link rel="stylesheet"> tag emitted by WP.
 * @param string $handle Registered handle.
 * @return string Filtered tag.
 */
function astra_child_seo_defer_stylesheets( $tag, $handle ) {
	if ( ! astra_child_seo_critical_css_should_run() ) {
		return $tag;
	}

	if ( ! is_string( $tag ) || '' === $tag ) {
		return $tag;
	}

	/**
	 * Stylesheet handles to keep render-blocking (i.e. not defer).
	 *
	 * Include any handle whose styles must apply before paint, e.g.
	 * a font loader or a critical library used by above-the-fold content.
	 *
	 * @param array<int,string> $handles
	 */
	$blocking = (array) apply_filters( 'astra_child_seo_critical_css_handles', array() );

	if ( in_array( $handle, $blocking, true ) ) {
		return $tag;
	}

	// Skip stylesheets that are already non-blocking (print-only or already deferred).
	if ( false !== strpos( $tag, "media='print'" )
		|| false !== strpos( $tag, 'media="print"' )
		|| false !== strpos( $tag, 'data-ac-deferred' ) ) {
		return $tag;
	}

	// Capture original media value (default "all").
	$original_media = 'all';
	if ( preg_match( '/media=([\'"])(.*?)\1/', $tag, $matches ) ) {
		$original_media = $matches[2];
	}

	if ( 'print' === $original_media ) {
		return $tag;
	}

	$onload = sprintf( "this.media='%s';this.onload=null", esc_attr( $original_media ) );

	// Replace existing media attribute, or inject one if absent.
	if ( preg_match( '/media=[\'"][^\'"]*[\'"]/', $tag ) ) {
		$deferred = preg_replace(
			'/media=[\'"][^\'"]*[\'"]/',
			'media="print" onload="' . $onload . '" data-ac-deferred="1"',
			$tag,
			1
		);
	} else {
		$deferred = preg_replace(
			'/<link\s/i',
			'<link media="print" onload="' . $onload . '" data-ac-deferred="1" ',
			$tag,
			1
		);
	}

	if ( ! is_string( $deferred ) || '' === $deferred ) {
		return $tag;
	}

	// Add a <noscript> fallback carrying the original tag (without our marker).
	return $deferred . '<noscript>' . $tag . '</noscript>';
}
add_filter( 'style_loader_tag', 'astra_child_seo_defer_stylesheets', 10, 2 );
