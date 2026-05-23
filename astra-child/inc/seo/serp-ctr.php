<?php
/**
 * SERP CTR module.
 *
 * Targets the highest-leverage organic search click-through-rate levers
 * that Yoast SEO Free does not enable by default:
 *
 *  1. Robots meta upgrade: max-snippet:-1, max-image-preview:large,
 *     max-video-preview:-1. Unlocks the largest snippet, image, and video
 *     previews. Documented to lift image-result CTR by 5-15% on rich
 *     content sites.
 *
 *  2. Year auto-stamp on evergreen titles. When a singular post title
 *     contains "best / guide / top / كيفية / دليل / أفضل" patterns and
 *     either lacks a year or carries a stale one, append or refresh the
 *     year so SERP listings read "...2026" instead of "...2024". Off by
 *     default; opt-in.
 *
 *  3. Reading-time signal. Counts content words once (Arabic-aware via
 *     Unicode word splitting), caches the result per post, and exposes
 *     it via Article schema "timeRequired" plus Twitter card label1 /
 *     data1 so search and social previews show "Reading time: 5 min"
 *     without any frontend rendering changes.
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --------------------------------------------------------------------- */
/*  Feature 1: enhanced robots meta                                      */
/* --------------------------------------------------------------------- */

/**
 * Append max-snippet / max-image-preview:large / max-video-preview to
 * the robots meta tag emitted by core via wp_robots().
 *
 * @param array<string,mixed> $directives Existing directives.
 * @return array<string,mixed>
 */
function astra_child_seo_ctr_robots( $directives ) {
	if ( ! apply_filters( 'astra_child_seo_ctr_robots_enabled', true ) ) {
		return $directives;
	}

	if ( ! empty( $directives['noindex'] ) ) {
		return $directives;
	}


	$extras = apply_filters(
		'astra_child_seo_ctr_robots_directives',
		array(
			'max-snippet'       => '-1',
			'max-image-preview' => 'large',
			'max-video-preview' => '-1',
		)
	);

	foreach ( $extras as $key => $value ) {
		if ( null === $value ) {
			unset( $directives[ $key ] );
			continue;
		}
		$directives[ $key ] = $value;
	}

	return $directives;
}
add_filter( 'wp_robots', 'astra_child_seo_ctr_robots', 99 );

/* --------------------------------------------------------------------- */
/*  Feature 2: year auto-stamp on evergreen titles                       */
/* --------------------------------------------------------------------- */

/**
 * Append or refresh the year on evergreen titles emitted by Yoast.
 *
 * Off by default. Enable per-site:
 *   add_filter( 'astra_child_seo_ctr_year_stamp_enabled', '__return_true' );
 *
 * @param string $title Title from Yoast.
 * @return string
 */
function astra_child_seo_ctr_year_stamp_yoast( $title ) {
	return astra_child_seo_ctr_apply_year_stamp( $title );
}
add_filter( 'wpseo_title', 'astra_child_seo_ctr_year_stamp_yoast', 99 );

/**
 * Same logic for sites that don't have Yoast active. WordPress generates
 * the document title via pre_get_document_title; we run after core.
 *
 * @param string $title Computed title.
 * @return string
 */
function astra_child_seo_ctr_year_stamp_core( $title ) {
	if ( '' === $title ) {
		return $title;
	}
	return astra_child_seo_ctr_apply_year_stamp( $title );
}
add_filter( 'pre_get_document_title', 'astra_child_seo_ctr_year_stamp_core', 99 );


/**
 * Apply the year stamp to a title string when applicable.
 *
 * @param string $title Title.
 * @return string
 */
function astra_child_seo_ctr_apply_year_stamp( $title ) {
	if ( is_admin() || is_feed() || ! is_singular() || '' === $title ) {
		return $title;
	}

	if ( ! apply_filters( 'astra_child_seo_ctr_year_stamp_enabled', false ) ) {
		return $title;
	}

	$patterns = apply_filters(
		'astra_child_seo_ctr_evergreen_patterns',
		array(
			// Arabic.
			'دليل',
			'أفضل',
			'افضل',
			'شرح',
			'كيفية',
			'مقارنة',
			'تعرف',
			// English.
			'best',
			'top',
			'guide',
			'how to',
			'tutorial',
			'review',
			'tips',
		)
	);

	$is_evergreen = false;
	foreach ( $patterns as $needle ) {
		if ( '' !== $needle && false !== mb_stripos( $title, (string) $needle ) ) {
			$is_evergreen = true;
			break;
		}
	}

	if ( ! $is_evergreen ) {
		return $title;
	}

	$current = (int) gmdate( 'Y' );
	$window  = max( 0, (int) apply_filters( 'astra_child_seo_ctr_year_window', 3 ) );
	$format  = (string) apply_filters( 'astra_child_seo_ctr_year_format', ' %d' );

	return astra_child_seo_ctr_inject_year( $title, $current, $window, $format );
}


/**
 * Insert / update the year inside a title, preserving any "| site name"
 * style suffix so the year sits next to the headline, not the brand.
 *
 * @param string $title   Source title.
 * @param int    $current Current year, e.g. 2026.
 * @param int    $window  Replace existing years up to N years stale.
 * @param string $format  sprintf format, e.g. " %d" or " (%d)".
 * @return string
 */
function astra_child_seo_ctr_inject_year( $title, $current, $window, $format ) {
	$separators = array( ' | ', ' - ', ' – ', ' — ', ' · ', ' » ', ' « ' );
	$head       = $title;
	$tail       = '';

	foreach ( $separators as $sep ) {
		$idx = mb_strrpos( $title, $sep );
		if ( false !== $idx ) {
			$head = mb_substr( $title, 0, $idx );
			$tail = mb_substr( $title, $idx );
			break;
		}
	}

	if ( preg_match_all( '/\b(20\d{2})\b/u', $head, $matches ) ) {
		$years  = array_map( 'intval', $matches[1] );
		$latest = max( $years );
		if ( $latest < $current && ( $current - $latest ) <= $window ) {
			$head = preg_replace(
				'/\b' . preg_quote( (string) $latest, '/' ) . '\b/u',
				(string) $current,
				$head,
				1
			);
		}
		return $head . $tail;
	}

	return $head . sprintf( $format, $current ) . $tail;
}

/* --------------------------------------------------------------------- */
/*  Feature 3: reading-time signal                                       */
/* --------------------------------------------------------------------- */

/**
 * Compute and cache the reading time of a post.
 *
 * @param WP_Post $post Post object.
 * @return int Minutes (>= 1).
 */
function astra_child_seo_ctr_reading_time( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return 0;
	}


	$cached = get_post_meta( $post->ID, '_astra_child_reading_minutes', true );
	if ( '' !== $cached ) {
		return (int) $cached;
	}

	$content = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
	$tokens  = preg_split( '/[\s\p{Z}\p{P}]+/u', $content, -1, PREG_SPLIT_NO_EMPTY );
	$count   = is_array( $tokens ) ? count( $tokens ) : 0;

	$wpm = (int) apply_filters( 'astra_child_seo_ctr_reading_wpm', 200 );
	if ( $wpm < 50 ) {
		$wpm = 50;
	}

	$minutes = max( 1, (int) ceil( $count / $wpm ) );

	update_post_meta( $post->ID, '_astra_child_reading_minutes', $minutes );

	return $minutes;
}

/**
 * Bust the cached reading time when a post is updated or deleted.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function astra_child_seo_ctr_bust_reading_cache( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	delete_post_meta( $post_id, '_astra_child_reading_minutes' );
}
add_action( 'save_post', 'astra_child_seo_ctr_bust_reading_cache' );
add_action( 'deleted_post', 'astra_child_seo_ctr_bust_reading_cache' );

/**
 * Add timeRequired (ISO 8601 duration) to the Yoast Article schema node.
 *
 * @param array<string,mixed> $data Article schema.
 * @return array<string,mixed>
 */
function astra_child_seo_ctr_article_time( $data ) {
	if ( ! is_array( $data ) || ! is_singular( 'post' ) ) {
		return $data;
	}
	if ( ! apply_filters( 'astra_child_seo_ctr_reading_time_enabled', true ) ) {
		return $data;
	}


	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return $data;
	}
	$minutes = astra_child_seo_ctr_reading_time( $post );
	if ( $minutes > 0 ) {
		$data['timeRequired'] = 'PT' . $minutes . 'M';
	}
	return $data;
}
add_filter( 'wpseo_schema_article', 'astra_child_seo_ctr_article_time', 99 );

/**
 * Print Twitter-card label / data tags so Twitter and other consumers of
 * the meta can show the reading-time hint without any extra JS or CSS.
 *
 * @return void
 */
function astra_child_seo_ctr_twitter_reading_time() {
	if ( is_admin() || is_feed() || ! is_singular( 'post' ) ) {
		return;
	}
	if ( ! apply_filters( 'astra_child_seo_ctr_reading_time_enabled', true ) ) {
		return;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$minutes = astra_child_seo_ctr_reading_time( $post );
	if ( $minutes <= 0 ) {
		return;
	}

	$label  = (string) apply_filters( 'astra_child_seo_ctr_reading_label', __( 'وقت القراءة', 'astra-child' ) );
	$format = (string) apply_filters( 'astra_child_seo_ctr_reading_value_format', __( '%d دقيقة', 'astra-child' ) );
	$value  = sprintf( $format, $minutes );

	printf( '<meta name="twitter:label1" content="%s" />' . "\n", esc_attr( $label ) );
	printf( '<meta name="twitter:data1" content="%s" />' . "\n", esc_attr( $value ) );
}
add_action( 'wp_head', 'astra_child_seo_ctr_twitter_reading_time', 30 );
