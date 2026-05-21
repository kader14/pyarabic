<?php
/**
 * Performance & technical SEO tweaks.
 *
 * - Adds resource hints (preconnect / dns-prefetch) for third parties so they
 *   resolve early, improving LCP and FID.
 * - Disables the WordPress emoji loader (saves ~13KB and one HTTP request).
 * - Optionally disables oEmbed discovery and the wp-embed.min.js script.
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add preconnect / dns-prefetch hints for known third parties.
 *
 * @param array<int,string|array<string,string>> $urls          Existing hints.
 * @param string                                 $relation_type One of dns-prefetch|preconnect|prefetch|prerender.
 * @return array<int,string|array<string,string>>
 */
function astra_child_seo_resource_hints( $urls, $relation_type ) {
	$preconnect = array(
		'https://pagead2.googlesyndication.com',
		'https://googleads.g.doubleclick.net',
		'https://www.googletagmanager.com',
		'https://fonts.googleapis.com',
		'https://fonts.gstatic.com',
	);

	$dns_prefetch = array(
		'//www.google-analytics.com',
		'//stats.g.doubleclick.net',
		'//adservice.google.com',
	);

	if ( 'preconnect' === $relation_type ) {
		foreach ( $preconnect as $host ) {
			$urls[] = array(
				'href'        => $host,
				'crossorigin' => 'anonymous',
			);
		}
	}

	if ( 'dns-prefetch' === $relation_type ) {
		foreach ( $dns_prefetch as $host ) {
			$urls[] = $host;
		}
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'astra_child_seo_resource_hints', 10, 2 );

/**
 * Disable the emoji detection / styles bundle on the front end.
 *
 * Smileys typed as text still render as native unicode in modern browsers.
 */
function astra_child_seo_disable_emojis() {
	if ( ! apply_filters( 'astra_child_seo_disable_emojis', true ) ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'tiny_mce_plugins', 'astra_child_seo_disable_emojis_tinymce' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'astra_child_seo_disable_emojis' );

/**
 * Remove the wpemoji plugin from TinyMCE.
 *
 * @param array<int,string> $plugins TinyMCE plugins.
 * @return array<int,string>
 */
function astra_child_seo_disable_emojis_tinymce( $plugins ) {
	if ( is_array( $plugins ) ) {
		return array_diff( $plugins, array( 'wpemoji' ) );
	}

	return array();
}

/**
 * Optionally disable the wp-embed.min.js script and oEmbed discovery.
 *
 * Off by default. Enable with:
 *   add_filter( 'astra_child_seo_disable_embeds', '__return_true' );
 */
function astra_child_seo_disable_embeds() {
	if ( ! apply_filters( 'astra_child_seo_disable_embeds', false ) ) {
		return;
	}

	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	add_filter(
		'tiny_mce_plugins',
		static function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, array( 'wpembed' ) ) : array();
		}
	);
	add_filter(
		'rewrite_rules_array',
		static function ( $rules ) {
			if ( ! is_array( $rules ) ) {
				return $rules;
			}
			foreach ( $rules as $rule => $rewrite ) {
				if ( false !== strpos( $rewrite, 'embed=true' ) ) {
					unset( $rules[ $rule ] );
				}
			}
			return $rules;
		}
	);
}
add_action( 'init', 'astra_child_seo_disable_embeds', 9999 );

/**
 * Defer non-critical front-end scripts so they don't block rendering.
 *
 * Keeps jQuery and any handle listed in the filter blocking by default
 * because some themes/plugins still rely on synchronous load order.
 *
 * @param string $tag    Full <script> tag.
 * @param string $handle Registered script handle.
 * @return string
 */
function astra_child_seo_defer_scripts( $tag, $handle ) {
	if ( is_admin() ) {
		return $tag;
	}

	$blocking = apply_filters(
		'astra_child_seo_blocking_scripts',
		array( 'jquery-core', 'jquery-migrate', 'jquery' )
	);

	if ( in_array( $handle, $blocking, true ) ) {
		return $tag;
	}

	if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
		return $tag;
	}

	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'astra_child_seo_defer_scripts', 10, 2 );
