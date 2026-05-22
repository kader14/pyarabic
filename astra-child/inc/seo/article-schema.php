<?php
/**
 * Advanced Article / NewsArticle schema enhancements.
 *
 * Yoast SEO Free emits a sensible Article node, but it is missing several
 * fields that meaningfully improve Rich Result eligibility for content
 * sites:
 *
 *   - @type stays "Article" even for posts that are clearly news.
 *     Switching to NewsArticle (when configured) is the single biggest
 *     lever for Google News / Discover surfaces.
 *   - articleSection (the section/category the article belongs to).
 *   - keywords (used by Discover and on-SERP enrichment).
 *   - image as an array of multiple resolutions, which Discover uses to
 *     pick the best aspect ratio.
 *   - thumbnailUrl (a smaller dedicated thumbnail).
 *   - speakable (lets Google Assistant read the headline / first
 *     paragraph aloud, valuable for Arabic TTS).
 *   - copyrightYear / copyrightHolder.
 *   - wordCount fallback that counts Arabic words correctly.
 *
 * Everything is filterable, and the module is a no-op when Yoast SEO
 * is not active because wpseo_schema_article never fires.
 *
 * @package Astra Child
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decide whether a post should be treated as a NewsArticle.
 *
 * Default rules:
 *   - It belongs to a category whose slug or name is one of:
 *     news, أخبار, اخبار (filterable).
 *   - It is a post of type "news" (filterable).
 *   - It has meta key _is_news set to a truthy value.
 *
 * Override completely with the astra_child_seo_is_news_post filter.
 *
 * @param WP_Post $post Post being rendered.
 * @return bool
 */
function astra_child_seo_is_news_post( $post ) {
	if ( ! ( $post instanceof WP_Post ) ) {
		return false;
	}

	$news_categories = (array) apply_filters(
		'astra_child_seo_news_categories',
		array( 'news', 'أخبار', 'اخبار' )
	);

	if ( ! empty( $news_categories ) && has_category( $news_categories, $post ) ) {
		return (bool) apply_filters( 'astra_child_seo_is_news_post', true, $post );
	}

	$news_post_types = (array) apply_filters( 'astra_child_seo_news_post_types', array( 'news' ) );
	if ( in_array( $post->post_type, $news_post_types, true ) ) {
		return (bool) apply_filters( 'astra_child_seo_is_news_post', true, $post );
	}

	if ( get_post_meta( $post->ID, '_is_news', true ) ) {
		return (bool) apply_filters( 'astra_child_seo_is_news_post', true, $post );
	}

	return (bool) apply_filters( 'astra_child_seo_is_news_post', false, $post );
}

/**
 * Resolve articleSection from the primary or first category.
 *
 * Honors Yoast's _yoast_wpseo_primary_category meta when present.
 *
 * @param WP_Post $post Post object.
 * @return string Section name, or empty string.
 */
function astra_child_seo_article_section( $post ) {
	if ( ! ( $post instanceof WP_Post ) ) {
		return '';
	}

	$primary_id = (int) get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true );
	if ( $primary_id > 0 ) {
		$term = get_term( $primary_id, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (string) $term->name;
		}
	}

	$categories = get_the_category( $post->ID );
	if ( ! empty( $categories ) ) {
		return (string) $categories[0]->name;
	}

	return '';
}

/**
 * Build the keywords array from the post's tags.
 *
 * Returns an empty string when the post has no tags so the field is
 * omitted rather than emitted empty.
 *
 * @param WP_Post $post Post object.
 * @return string Comma-separated keywords, or empty string.
 */
function astra_child_seo_article_keywords( $post ) {
	if ( ! ( $post instanceof WP_Post ) ) {
		return '';
	}

	$tags = get_the_tags( $post->ID );
	if ( empty( $tags ) || is_wp_error( $tags ) ) {
		return '';
	}

	$names = array_filter( wp_list_pluck( $tags, 'name' ) );
	return implode( ', ', $names );
}

/**
 * Build a multi-resolution ImageObject array from the featured image.
 *
 * Google Discover and News pick the best aspect ratio from the array,
 * so we offer the same source at full / large / medium_large sizes
 * (without forcing thumbnail regeneration).
 *
 * @param WP_Post $post Post object.
 * @return array<int,array<string,mixed>>
 */
function astra_child_seo_article_images( $post ) {
	if ( ! ( $post instanceof WP_Post ) || ! has_post_thumbnail( $post->ID ) ) {
		return array();
	}

	$thumb_id = (int) get_post_thumbnail_id( $post->ID );
	$images   = array();
	$seen     = array();

	$sizes = (array) apply_filters(
		'astra_child_seo_article_image_sizes',
		array( 'full', 'large', 'medium_large' )
	);

	foreach ( $sizes as $size ) {
		$src = wp_get_attachment_image_src( $thumb_id, $size );
		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			continue;
		}

		// Avoid duplicates when the original is smaller than 'large'.
		if ( isset( $seen[ $src[0] ] ) ) {
			continue;
		}
		$seen[ $src[0] ] = true;

		$images[] = array(
			'@type'  => 'ImageObject',
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}

	return $images;
}

/**
 * Count words in a string using a Unicode-aware split.
 *
 * PHP's str_word_count only understands ASCII letters, which makes it
 * useless for Arabic text. We split on any whitespace and count the
 * non-empty pieces.
 *
 * @param string $text Source text.
 * @return int Word count.
 */
function astra_child_seo_count_words( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

	if ( '' === $text ) {
		return 0;
	}

	$parts = preg_split( '/\s+/u', $text );
	return is_array( $parts ) ? count( array_filter( $parts ) ) : 0;
}

/**
 * Filter the Yoast Article schema piece and add the missing fields.
 *
 * @param array<string,mixed> $data    Schema piece data.
 * @param object              $context Yoast meta_tags_context (loose typed for cross-version safety).
 * @return array<string,mixed>
 */
function astra_child_seo_article_filter( $data, $context = null ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	if ( ! is_singular() ) {
		return $data;
	}

	$post = get_queried_object();
	if ( ! ( $post instanceof WP_Post ) ) {
		return $data;
	}

	// Switch @type to NewsArticle when applicable.
	if ( astra_child_seo_is_news_post( $post ) ) {
		$data['@type'] = 'NewsArticle';
	}

	// articleSection from primary/first category.
	$section = astra_child_seo_article_section( $post );
	if ( '' !== $section && empty( $data['articleSection'] ) ) {
		$data['articleSection'] = $section;
	}

	// keywords from tags.
	$keywords = astra_child_seo_article_keywords( $post );
	if ( '' !== $keywords && empty( $data['keywords'] ) ) {
		$data['keywords'] = $keywords;
	}

	// Multi-resolution image array.
	if ( apply_filters( 'astra_child_seo_article_replace_images', true ) ) {
		$images = astra_child_seo_article_images( $post );
		if ( ! empty( $images ) ) {
			$data['image'] = $images;
		}
	}

	// thumbnailUrl.
	if ( has_post_thumbnail( $post->ID ) && empty( $data['thumbnailUrl'] ) ) {
		$thumb_url = get_the_post_thumbnail_url( $post->ID, 'medium' );
		if ( $thumb_url ) {
			$data['thumbnailUrl'] = esc_url_raw( $thumb_url );
		}
	}

	// SpeakableSpecification - improves Google Assistant TTS pickup.
	if ( apply_filters( 'astra_child_seo_article_enable_speakable', true ) ) {
		$selectors = (array) apply_filters(
			'astra_child_seo_article_speakable_selectors',
			array(
				'h1.entry-title',
				'.entry-content > p:first-of-type',
			)
		);

		if ( ! empty( $selectors ) ) {
			$data['speakable'] = array(
				'@type'       => 'SpeakableSpecification',
				'cssSelector' => array_values( $selectors ),
			);
		}
	}

	// copyrightYear from publication date.
	if ( empty( $data['copyrightYear'] ) ) {
		$data['copyrightYear'] = (int) mysql2date( 'Y', $post->post_date_gmt ?: $post->post_date );
	}

	// copyrightHolder = the site Organization node Yoast already emits.
	if ( empty( $data['copyrightHolder'] ) ) {
		$site_url = is_object( $context ) && ! empty( $context->site_url )
			? $context->site_url
			: home_url();
		$data['copyrightHolder'] = array(
			'@id' => trailingslashit( $site_url ) . '#organization',
		);
	}

	// wordCount fallback (Arabic-aware).
	if ( empty( $data['wordCount'] ) || (int) $data['wordCount'] <= 0 ) {
		$word_count = astra_child_seo_count_words( $post->post_content );
		if ( $word_count > 0 ) {
			$data['wordCount'] = $word_count;
		}
	}

	/**
	 * Final filter to let site-specific code add custom fields
	 * (sponsor, isAccessibleForFree, video, etc.) without copying the
	 * whole module.
	 *
	 * @param array<string,mixed> $data    Schema piece.
	 * @param WP_Post             $post    Current post.
	 * @param object|null         $context Yoast context.
	 */
	return (array) apply_filters( 'astra_child_seo_article_data', $data, $post, $context );
}
add_filter( 'wpseo_schema_article', 'astra_child_seo_article_filter', 99, 2 );
