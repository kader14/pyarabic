<?php
/**
 * Tweaks that improve the output of Yoast SEO Free.
 *
 * Each function bails silently if Yoast is not active so disabling the plugin
 * never produces fatal errors.
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Move the Yoast meta box to a lower priority on the post edit screen.
 *
 * Custom fields and the publish box are usually more useful to editors.
 *
 * @return string
 */
function astra_child_seo_yoast_metabox_priority() {
	return 'low';
}
add_filter( 'wpseo_metabox_prio', 'astra_child_seo_yoast_metabox_priority' );

/**
 * Provide a Twitter site handle to Yoast if the admin hasn't set one.
 *
 * Configure with:
 *   define( 'ASTRA_CHILD_SEO_TWITTER_HANDLE', '@pyarabic' );
 * or via the filter astra_child_seo_twitter_handle.
 *
 * @param string $handle Existing handle (with or without @).
 * @return string
 */
function astra_child_seo_yoast_twitter_site( $handle ) {
	if ( ! empty( $handle ) ) {
		return $handle;
	}

	$default = defined( 'ASTRA_CHILD_SEO_TWITTER_HANDLE' ) ? ASTRA_CHILD_SEO_TWITTER_HANDLE : '';

	/**
	 * Filter the Twitter site handle used as a fallback.
	 *
	 * @param string $default Twitter handle, with or without leading @.
	 */
	$handle = apply_filters( 'astra_child_seo_twitter_handle', $default );

	return is_string( $handle ) ? $handle : '';
}
add_filter( 'wpseo_twitter_site', 'astra_child_seo_yoast_twitter_site' );

/**
 * Provide a default Open Graph image when a post has no featured image.
 *
 * Configure with:
 *   define( 'ASTRA_CHILD_SEO_DEFAULT_OG_IMAGE', 'https://example.com/og.jpg' );
 *
 * @param string $image Existing OG image URL chosen by Yoast.
 * @return string
 */
function astra_child_seo_yoast_default_og_image( $image ) {
	if ( ! empty( $image ) ) {
		return $image;
	}

	$default = defined( 'ASTRA_CHILD_SEO_DEFAULT_OG_IMAGE' ) ? ASTRA_CHILD_SEO_DEFAULT_OG_IMAGE : '';

	/**
	 * Filter the fallback Open Graph image URL.
	 *
	 * @param string $default Absolute URL.
	 */
	$default = apply_filters( 'astra_child_seo_default_og_image', $default );

	return is_string( $default ) ? esc_url_raw( $default ) : '';
}
add_filter( 'wpseo_opengraph_image', 'astra_child_seo_yoast_default_og_image' );

/**
 * Force Yoast to use the full-size featured image for Open Graph and
 * Twitter card previews.
 *
 * Yoast defaults to the WordPress `large` size (1024px wide), which is
 * smaller than the 1200px minimum that Google requires to actually honor
 * `max-image-preview:large` in SERP and Discover. With `large`, Google
 * downgrades to the smaller "thumbnail" preview even though the robots
 * directive permits the large one. Switching to `full` uses the original
 * upload, which is typically >= 1200px wide for modern uploads (Gemini,
 * Midjourney, DSLR, modern phones, etc).
 *
 * Configurable via the `astra_child_seo_og_image_size` filter for sites
 * that prefer a different size.
 *
 * @param string $size Image size keyword passed to wp_get_attachment_image_src.
 * @return string
 */
function astra_child_seo_yoast_og_image_size( $size ) {
	$override = apply_filters( 'astra_child_seo_og_image_size', 'full' );
	return is_string( $override ) && '' !== $override ? $override : $size;
}
add_filter( 'wpseo_opengraph_image_size', 'astra_child_seo_yoast_og_image_size' );
add_filter( 'wpseo_twitter_image_size', 'astra_child_seo_yoast_og_image_size' );

/**
 * Add the article publisher to the Yoast schema graph for posts.
 *
 * Yoast Free already adds Organization schema globally, but it does not
 * always wire it into the Article node as the publisher when the site is
 * configured as a "Person". This makes Article -> publisher.@id resolvable.
 *
 * @param array<string,mixed> $data    Schema piece data.
 * @param object              $context Yoast meta_tags_context object (not type-hinted to keep this safe across versions).
 * @return array<string,mixed>
 */
function astra_child_seo_yoast_schema_article( $data, $context ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	if ( empty( $data['publisher'] ) && ! empty( $context->site_url ) ) {
		$data['publisher'] = array( '@id' => trailingslashit( $context->site_url ) . '#organization' );
	}

	return $data;
}
add_filter( 'wpseo_schema_article', 'astra_child_seo_yoast_schema_article', 10, 2 );
