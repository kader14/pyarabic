<?php
/**
 * Reading-time badge (frontend).
 *
 * Renders a small "X minutes read" badge near the title on single posts so
 * the on-page experience matches the reading-time hint we already advertise
 * via Twitter cards (twitter:label1 / twitter:data1) and Article schema
 * (timeRequired) in the serp-ctr module.
 *
 * The minutes value is read from the cache that serp-ctr populates on the
 * `_astra_child_reading_minutes` post meta - no extra computation here.
 *
 * Position resolution:
 *
 *  - 'auto' (default): use the Astra meta-line filter when the parent theme
 *    is Astra; otherwise prepend to `the_content`.
 *  - 'meta': always use the Astra meta-line filter.
 *  - 'content': always prepend to `the_content`.
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'astra_single_post_meta', 'astra_child_seo_reading_badge_extend_meta', 99 );
add_filter( 'the_content', 'astra_child_seo_reading_badge_prepend_content', 5 );
add_action( 'wp_head', 'astra_child_seo_reading_badge_print_css', 100 );



/**
 * Whether the badge should render in this request at all.
 *
 * @return bool
 */
function astra_child_seo_reading_badge_should_render() {
	if ( is_admin() || is_feed() || ! is_singular() ) {
		return false;
	}

	if ( ! apply_filters( 'astra_child_seo_reading_badge_enabled', true ) ) {
		return false;
	}

	$post_types = (array) apply_filters(
		'astra_child_seo_reading_badge_post_types',
		array( 'post' )
	);

	if ( ! in_array( get_post_type(), $post_types, true ) ) {
		return false;
	}

	return true;
}

/**
 * Resolve where the badge should render: 'meta' (Astra meta line) or
 * 'content' (prepended to post content).
 *
 * @return string Either 'meta' or 'content'.
 */
function astra_child_seo_reading_badge_resolve_position() {
	$position = (string) apply_filters( 'astra_child_seo_reading_badge_position', 'auto' );

	if ( 'meta' === $position || 'content' === $position ) {
		return $position;
	}

	// Auto: Astra parent active -> meta line; otherwise content.
	if ( defined( 'ASTRA_THEME_VERSION' ) || function_exists( 'astra_get_option' ) ) {
		return 'meta';
	}

	return 'content';
}



/**
 * Build the badge HTML for the current post.
 *
 * Returns an empty string when the badge should be skipped (post too short,
 * serp-ctr module disabled, etc.).
 *
 * @return string
 */
function astra_child_seo_reading_badge_html() {
	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	// Depend on the cached reading-time helper from the serp-ctr module.
	// If that module is disabled we silently skip - the badge has no value
	// without a real reading-time number.
	if ( ! function_exists( 'astra_child_seo_ctr_reading_time' ) ) {
		return '';
	}

	$minutes = (int) astra_child_seo_ctr_reading_time( $post );
	$min     = (int) apply_filters( 'astra_child_seo_reading_badge_min_minutes', 1 );

	if ( $minutes < $min ) {
		return '';
	}

	$format = (string) apply_filters(
		'astra_child_seo_reading_badge_format',
		/* translators: %d: minutes */
		__( '⏱ %d دقيقة قراءة', 'astra-child' )
	);

	$text = sprintf( $format, $minutes );

	return sprintf(
		'<span class="ac-reading-time" data-minutes="%d">%s</span>',
		$minutes,
		esc_html( $text )
	);
}



/**
 * Append the badge to Astra's single-post meta line.
 *
 * @param string $meta The existing meta HTML.
 * @return string
 */
function astra_child_seo_reading_badge_extend_meta( $meta ) {
	if ( ! astra_child_seo_reading_badge_should_render() ) {
		return $meta;
	}

	if ( 'meta' !== astra_child_seo_reading_badge_resolve_position() ) {
		return $meta;
	}

	$badge = astra_child_seo_reading_badge_html();
	if ( '' === $badge ) {
		return $meta;
	}

	// Mark that we rendered so the_content fallback knows to skip.
	$GLOBALS['astra_child_reading_badge_rendered'] = true;

	return $meta . $badge;
}

/**
 * Prepend the badge to post content (fallback when the meta line is not
 * the chosen position).
 *
 * @param string $content Post content.
 * @return string
 */
function astra_child_seo_reading_badge_prepend_content( $content ) {
	if ( ! astra_child_seo_reading_badge_should_render() ) {
		return $content;
	}

	if ( ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}

	if ( ! empty( $GLOBALS['astra_child_reading_badge_rendered'] ) ) {
		return $content;
	}

	if ( 'content' !== astra_child_seo_reading_badge_resolve_position() ) {
		return $content;
	}

	$badge = astra_child_seo_reading_badge_html();
	if ( '' === $badge ) {
		return $content;
	}

	$GLOBALS['astra_child_reading_badge_rendered'] = true;

	return '<p class="ac-reading-time-line">' . $badge . '</p>' . $content;
}



/**
 * Print the small inline CSS for the badge.
 *
 * Uses Astra's CSS custom properties when available so the badge inherits
 * the active palette; falls back to a neutral gray on non-Astra setups.
 *
 * @return void
 */
function astra_child_seo_reading_badge_print_css() {
	if ( ! astra_child_seo_reading_badge_should_render() ) {
		return;
	}

	$css  = '.ac-reading-time{display:inline-flex;align-items:center;';
	$css .= 'gap:.25em;font-size:.875em;font-weight:500;line-height:1;';
	$css .= 'color:var(--ast-global-color-3,#5b5b5b);vertical-align:middle}';
	$css .= '.ac-reading-time::before{content:"·";display:inline-block;';
	$css .= 'margin:0 .5em;opacity:.7;font-weight:400}';
	$css .= '.ac-reading-time-line{margin:0 0 1em;padding:0;';
	$css .= 'font-size:.95em;color:var(--ast-global-color-3,#5b5b5b)}';
	$css .= '.ac-reading-time-line .ac-reading-time::before{content:none}';

	echo '<style id="ac-reading-time-badge-css">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
