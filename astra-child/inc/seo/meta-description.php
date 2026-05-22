<?php
/**
 * Meta description hardening and smart fallback.
 *
 * Improves the quality of the <meta name="description"> tag emitted by
 * Yoast SEO Free in three ways:
 *
 *   1. Cleans whatever Yoast outputs: strips shortcodes, HTML tags,
 *      emoji, decodes entities, normalizes whitespace, removes leading
 *      bylines like "By author |".
 *
 *   2. Provides a smart fallback when Yoast's value is empty or too
 *      short. Walks: post excerpt -> first paragraph of content -> term
 *      description -> site tagline.
 *
 *   3. Trims the result to an Arabic-aware character length without
 *      cutting words in half, and adds an ellipsis when truncated.
 *
 * All length thresholds and behaviors are filterable. The module never
 * touches a value Yoast emits if it's already in the ideal range.
 *
 * @package Astra Child
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default minimum length in characters.
 *
 * Below this threshold the cleaned Yoast value is considered "too short"
 * and the fallback chain takes over.
 */
const ASTRA_CHILD_SEO_METADESC_MIN = 80;

/**
 * Default maximum length in characters.
 *
 * Google truncates on SERPs at roughly 155 chars desktop / 120 mobile.
 * Arabic letters render slightly wider so we target the lower end.
 */
const ASTRA_CHILD_SEO_METADESC_MAX = 155;

/**
 * Trim a string to a max character length without breaking words.
 *
 * Walks back from the cut point to the previous whitespace so we never
 * end mid-word. Adds the Unicode horizontal ellipsis when truncated.
 *
 * @param string   $text Source text.
 * @param int|null $max  Optional override for max chars.
 * @return string
 */
function astra_child_seo_metadesc_trim( $text, $max = null ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return '';
	}

	if ( null === $max ) {
		/**
		 * Filter the maximum allowed meta description length in chars.
		 *
		 * @param int $max
		 */
		$max = (int) apply_filters( 'astra_child_seo_metadesc_max', ASTRA_CHILD_SEO_METADESC_MAX );
	}

	if ( $max <= 0 || mb_strlen( $text, 'UTF-8' ) <= $max ) {
		return $text;
	}

	$cut = mb_substr( $text, 0, $max - 1, 'UTF-8' );

	$last_space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
	if ( false !== $last_space && $last_space > $max / 2 ) {
		$cut = mb_substr( $cut, 0, $last_space, 'UTF-8' );
	}

	// Strip trailing punctuation that looks weird before an ellipsis.
	$cut = rtrim( $cut, " \t\n\r,.،؛:-" );

	return $cut . '…';
}

/**
 * Clean a raw description string.
 *
 * Order of operations matters: strip shortcodes first (they may contain
 * angle brackets), then HTML, decode entities, then whitespace.
 *
 * @param string $text Source text.
 * @return string
 */
function astra_child_seo_metadesc_clean( $text ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return '';
	}

	$text = strip_shortcodes( $text );
	$text = wp_strip_all_tags( $text, true );

	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// Strip the most common emoji ranges. Lightweight, not exhaustive.
	$text = preg_replace(
		'/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1FA70}-\x{1FAFF}]/u',
		'',
		(string) $text
	);

	// Drop leading "by author |" / "by author -" patterns.
	$text = preg_replace( '/^(by\s+\S+(\s+\S+)?\s*[|\-\x{2013}\x{2014}:]\s*)/iu', '', (string) $text );

	// Collapse runs of whitespace (incl. NBSPs) to single spaces.
	$text = preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $text );

	return trim( (string) $text );
}

/**
 * Compute a fallback description from the current queried object.
 *
 * @return string
 */
function astra_child_seo_metadesc_fallback() {
	if ( is_singular() ) {
		$post = get_queried_object();

		if ( $post instanceof WP_Post ) {
			if ( ! empty( $post->post_excerpt ) ) {
				return astra_child_seo_metadesc_clean( $post->post_excerpt );
			}

			/**
			 * Filter the raw post content used to derive the fallback.
			 *
			 * @param string  $content Raw post_content.
			 * @param WP_Post $post    Current post.
			 */
			$content = (string) apply_filters( 'astra_child_seo_metadesc_content', $post->post_content, $post );

			return astra_child_seo_metadesc_clean( $content );
		}
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( isset( $term->description ) && '' !== trim( (string) $term->description ) ) {
			return astra_child_seo_metadesc_clean( $term->description );
		}
	}

	if ( is_author() ) {
		$author = get_queried_object();
		if ( $author && isset( $author->ID ) ) {
			$bio = get_the_author_meta( 'description', $author->ID );
			if ( ! empty( $bio ) ) {
				return astra_child_seo_metadesc_clean( $bio );
			}
		}
	}

	$tagline = (string) get_bloginfo( 'description', 'display' );
	return astra_child_seo_metadesc_clean( $tagline );
}

/**
 * Filter the meta description Yoast (or core) is about to emit.
 *
 * Runs at priority 99 so we are the last word.
 *
 * @param string $desc Existing description.
 * @return string
 */
function astra_child_seo_metadesc_filter( $desc ) {
	$desc = astra_child_seo_metadesc_clean( (string) $desc );

	$min = (int) apply_filters( 'astra_child_seo_metadesc_min', ASTRA_CHILD_SEO_METADESC_MIN );

	if ( '' === $desc || mb_strlen( $desc, 'UTF-8' ) < $min ) {
		$fallback = astra_child_seo_metadesc_fallback();
		if ( '' !== $fallback ) {
			$desc = $fallback;
		}
	}

	$desc = astra_child_seo_metadesc_trim( $desc );

	/**
	 * Final filter on the meta description before output.
	 *
	 * Useful if you want to append a fixed call-to-action like
	 * " - اقرأ المزيد على pyarabic.com".
	 *
	 * @param string $desc
	 */
	return (string) apply_filters( 'astra_child_seo_metadesc_final', $desc );
}
add_filter( 'wpseo_metadesc', 'astra_child_seo_metadesc_filter', 99 );
add_filter( 'wpseo_opengraph_desc', 'astra_child_seo_metadesc_filter', 99 );
add_filter( 'wpseo_twitter_description', 'astra_child_seo_metadesc_filter', 99 );

/**
 * Optionally pre-fill the Yoast meta description on save_post.
 *
 * Disabled by default. Enable with:
 *   add_filter( 'astra_child_seo_metadesc_autosave', '__return_true' );
 *
 * Only fills when the editor left the field empty so it never overrides
 * a manually crafted description.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function astra_child_seo_metadesc_autosave_handler( $post_id, $post ) {
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
		return;
	}

	if ( ! apply_filters( 'astra_child_seo_metadesc_autosave', false ) ) {
		return;
	}

	$post_types = (array) apply_filters(
		'astra_child_seo_metadesc_post_types',
		array( 'post', 'page' )
	);

	if ( ! in_array( $post->post_type, $post_types, true ) ) {
		return;
	}

	$existing = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
	if ( '' !== trim( $existing ) ) {
		return;
	}

	$source      = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;
	$cleaned     = astra_child_seo_metadesc_clean( $source );
	$description = astra_child_seo_metadesc_trim( $cleaned );

	if ( '' === $description ) {
		return;
	}

	update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );
}
add_action( 'save_post', 'astra_child_seo_metadesc_autosave_handler', 20, 2 );

/**
 * Show a non-blocking admin notice when an editor publishes a post
 * without a Yoast meta description set.
 *
 * Lives behind a filter so quiet admins can disable it.
 *
 * @return void
 */
function astra_child_seo_metadesc_admin_notice() {
	if ( ! apply_filters( 'astra_child_seo_metadesc_admin_notice', true ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}

	global $post;
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$existing = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
	if ( '' !== trim( $existing ) ) {
		return;
	}

	if ( 'publish' !== $post->post_status ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'No SEO meta description is set on this post. A fallback will be auto-generated, but writing a custom one in the Yoast SEO box improves click-through.', 'astra-child' )
	);
}
add_action( 'admin_notices', 'astra_child_seo_metadesc_admin_notice' );
