<?php
/**
 * Image SEO tweaks.
 *
 * - When an image has no alt text, fall back to the attachment title or
 *   the post title. Better than an empty alt (Yoast complains, screen
 *   readers ignore it, Google can't read it).
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fill in a missing alt attribute on the fly when an image is rendered.
 *
 * Does not mutate database values. If you want that, run a one-off
 * WP-CLI script that calls update_post_meta( $id, '_wp_attachment_image_alt', ... ).
 *
 * @param array<string,string>|false $attr       Image attributes.
 * @param object|int                 $attachment Attachment post object or ID.
 * @return array<string,string>
 */
function astra_child_seo_image_alt_fallback( $attr, $attachment ) {
	if ( ! is_array( $attr ) ) {
		$attr = array();
	}

	if ( ! empty( $attr['alt'] ) ) {
		return $attr;
	}

	$attachment_id = is_object( $attachment ) ? (int) $attachment->ID : (int) $attachment;

	if ( $attachment_id <= 0 ) {
		return $attr;
	}

	$post = get_post( $attachment_id );

	if ( $post && ! empty( $post->post_title ) ) {
		$attr['alt'] = wp_strip_all_tags( $post->post_title );
		return $attr;
	}

	$parent_id = $post ? (int) $post->post_parent : 0;
	if ( $parent_id > 0 ) {
		$parent_title = get_the_title( $parent_id );
		if ( ! empty( $parent_title ) ) {
			$attr['alt'] = wp_strip_all_tags( $parent_title );
		}
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'astra_child_seo_image_alt_fallback', 10, 2 );

/**
 * Same fallback but for images inserted into post content via the_content.
 *
 * Looks for <img ...> tags whose alt is empty or missing and fills them
 * using the surrounding figure caption or the post title.
 *
 * @param string $content Post HTML content.
 * @return string
 */
function astra_child_seo_image_alt_fallback_in_content( $content ) {
	if ( ! is_string( $content ) || '' === trim( $content ) || false === strpos( $content, '<img' ) ) {
		return $content;
	}

	$post_title = get_the_title();
	if ( '' === $post_title ) {
		return $content;
	}

	$replacement = sprintf( ' alt="%s"', esc_attr( wp_strip_all_tags( $post_title ) ) );

	$content = preg_replace( '/<img((?:(?!\salt=)[^>])*?)>/i', '<img$1' . $replacement . '>', $content );
	$content = preg_replace( '/<img([^>]*)\salt=""([^>]*)>/i', '<img$1' . $replacement . '$2>', $content );

	return $content;
}
add_filter( 'the_content', 'astra_child_seo_image_alt_fallback_in_content', 25 );
