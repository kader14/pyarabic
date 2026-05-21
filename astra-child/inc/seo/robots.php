<?php
/**
 * robots.txt enhancements.
 *
 * This module only fires when WordPress is generating robots.txt dynamically
 * (i.e. there is NO physical robots.txt file in the document root). When a
 * physical file exists, Apache/Nginx serves it directly and this filter is
 * never invoked.
 *
 * What it adds:
 *   - The Yoast SEO sitemap (only if not already present, to avoid duplicates).
 *   - Block rules for aggressive third-party SEO scrapers.
 *   - Disallow rules for low-value WordPress endpoints.
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the sitemap URL.
 *
 * Order of precedence:
 *   1. The constant ASTRA_CHILD_SEO_SITEMAP_URL.
 *   2. The filter astra_child_seo_sitemap_url.
 *   3. home_url( '/sitemap_index.xml' ) which is Yoast's default.
 *
 * @return string Absolute URL to the sitemap (or sitemap index).
 */
function astra_child_seo_robots_sitemap_url() {
	$default = home_url( '/sitemap_index.xml' );

	if ( defined( 'ASTRA_CHILD_SEO_SITEMAP_URL' ) && '' !== ASTRA_CHILD_SEO_SITEMAP_URL ) {
		$default = ASTRA_CHILD_SEO_SITEMAP_URL;
	}

	/**
	 * Filter the sitemap URL announced in robots.txt.
	 *
	 * @param string $default Sitemap URL.
	 */
	$url = apply_filters( 'astra_child_seo_sitemap_url', $default );

	return is_string( $url ) ? esc_url_raw( $url ) : $default;
}

/**
 * Append rules to the WordPress-generated robots.txt output.
 *
 * @param string $output Current robots.txt body.
 * @param bool   $public Whether the site is public (false when admin sets "Discourage search engines").
 * @return string
 */
function astra_child_seo_filter_robots_txt( $output, $public ) {
	if ( ! is_string( $output ) ) {
		$output = '';
	}

	// If the admin has set "Discourage search engines from indexing this site",
	// WP outputs a Disallow: / for everyone. Don't fight that.
	if ( ! $public ) {
		return $output;
	}

	$lines = array();

	// 1) Disallow low-value endpoints (only if not already blocked).
	$extra_disallows = array(
		'/xmlrpc.php',
		'/wp-login.php',
		'/wp-signup.php',
		'/wp-activate.php',
		'/readme.html',
		'/license.txt',
		'/?s=',
		'/search/',
		'/*?replytocom=',
		'/trackback/',
		'/*/trackback/',
		'/*?utm_*',
		'/*?fbclid=',
		'/*?gclid=',
	);

	foreach ( $extra_disallows as $path ) {
		$rule = 'Disallow: ' . $path;
		if ( false === strpos( $output, $rule ) ) {
			$lines[] = $rule;
		}
	}

	// 2) Block aggressive SEO scraper bots.
	$blocked_bots = apply_filters(
		'astra_child_seo_blocked_bots',
		array(
			'SemrushBot',
			'AhrefsBot',
			'MJ12bot',
			'DotBot',
			'BLEXBot',
			'SeznamBot',
		)
	);

	if ( ! empty( $blocked_bots ) && is_array( $blocked_bots ) ) {
		$lines[] = '';
		$lines[] = '# Aggressive SEO scrapers blocked by astra-child.';
		foreach ( $blocked_bots as $bot ) {
			$bot = trim( (string) $bot );
			if ( '' === $bot ) {
				continue;
			}
			$lines[] = 'User-agent: ' . $bot;
			$lines[] = 'Disallow: /';
			$lines[] = '';
		}
	}

	// 3) Sitemap directive (only if no Sitemap line already exists - Yoast may
	//    have added it via its own robots_txt filter, in which case we skip).
	if ( false === stripos( $output, 'Sitemap:' ) ) {
		$lines[] = 'Sitemap: ' . astra_child_seo_robots_sitemap_url();
	}

	if ( empty( $lines ) ) {
		return $output;
	}

	return rtrim( $output ) . "\n\n" . implode( "\n", $lines ) . "\n";
}
add_filter( 'robots_txt', 'astra_child_seo_filter_robots_txt', 99, 2 );
