<?php
/**
 * Arabic / RTL specific SEO tweaks.
 *
 * - Forces the Open Graph locale to ar_AR.
 * - Improves the auto excerpt for Arabic content (longer + Arabic ellipsis).
 * - Replaces the breadcrumb separator with one that reads better in RTL.
 * - Adds a hreflang fallback when no multi-language plugin is active.
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set Open Graph locale to ar_AR for Yoast SEO output.
 *
 * Yoast normally derives this from get_locale(). On some installs the locale
 * is just "ar" which Facebook treats as invalid; we force the BCP-47 form.
 *
 * @param string $locale Locale string.
 * @return string
 */
function astra_child_seo_force_arabic_locale( $locale ) {
	if ( in_array( strtolower( (string) $locale ), array( '', 'ar', 'ar_ar' ), true ) ) {
		return 'ar_AR';
	}

	return $locale;
}
add_filter( 'wpseo_locale', 'astra_child_seo_force_arabic_locale' );
add_filter( 'wpseo_opengraph_locale', 'astra_child_seo_force_arabic_locale' );

/**
 * Increase the default excerpt length for Arabic content.
 *
 * Arabic words are often shorter than English so 55 words can feel
 * abrupt. 35 words gives roughly the same character count as the
 * English default.
 *
 * @param int $length Default excerpt length in words.
 * @return int
 */
function astra_child_seo_excerpt_length( $length ) {
	return 35;
}
add_filter( 'excerpt_length', 'astra_child_seo_excerpt_length', 999 );

/**
 * Use an Arabic ellipsis at the end of auto excerpts.
 *
 * @param string $more Default "more" string.
 * @return string
 */
function astra_child_seo_excerpt_more( $more ) {
	return ' &hellip;';
}
add_filter( 'excerpt_more', 'astra_child_seo_excerpt_more' );

/**
 * Replace the Yoast breadcrumb separator with one that reads better in RTL.
 *
 * The default ">" looks like it points the wrong way in an RTL layout;
 * "&laquo;" or a plain "/" is friendlier.
 *
 * @param array<string,mixed> $options Yoast options array.
 * @return array<string,mixed>
 */
function astra_child_seo_breadcrumb_separator( $options ) {
	if ( ! is_array( $options ) ) {
		return $options;
	}

	if ( empty( $options['breadcrumbs-sep'] ) || '&raquo;' === $options['breadcrumbs-sep'] ) {
		$options['breadcrumbs-sep'] = '&laquo;';
	}

	return $options;
}
add_filter( 'wpseo_breadcrumb_links', 'astra_child_seo_breadcrumb_separator' );

/**
 * Output a self-referential hreflang tag for Arabic.
 *
 * Skipped if a multilingual plugin (Polylang, WPML, TranslatePress) is active
 * so we don't conflict with their hreflang output.
 */
function astra_child_seo_hreflang() {
	if ( defined( 'POLYLANG_VERSION' ) || defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'TRP_PLUGIN_VERSION' ) ) {
		return;
	}

	if ( is_admin() || is_feed() ) {
		return;
	}

	$url = is_singular() ? get_permalink() : home_url( add_query_arg( null, null ) );

	if ( empty( $url ) ) {
		return;
	}

	printf(
		'<link rel="alternate" hreflang="ar" href="%s" />' . "\n",
		esc_url( $url )
	);
	printf(
		'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
		esc_url( $url )
	);
}
add_action( 'wp_head', 'astra_child_seo_hreflang', 6 );
