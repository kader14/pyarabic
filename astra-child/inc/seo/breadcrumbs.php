<?php
/**
 * Breadcrumb schema repair.
 *
 * Fixes the Search Console error
 *
 *     Missing field "itemListElement" (in "BreadcrumbList")
 *
 * which Yoast SEO Free emits on some pages where the breadcrumb trail is
 * empty (home, 404, search, paginated archives) or where the parent theme
 * (Astra) leaks its own incomplete BreadcrumbList microdata.
 *
 * Two-pronged fix:
 *
 *   1. Suppress Astra's microdata so we keep a single, validated source
 *      of structured data.
 *
 *   2. Filter Yoast's wpseo_schema_breadcrumb piece. If itemListElement
 *      is missing or empty, rebuild it from the current request. If we
 *      genuinely cannot produce a meaningful trail, return false so
 *      Yoast drops the BreadcrumbList from the @graph entirely - which
 *      is preferable to emitting an invalid one.
 *
 * Disable the whole module:
 *   add_filter( 'astra_child_seo_module_breadcrumbs', '__return_false' );
 *
 * Keep Astra's breadcrumb schema (not recommended):
 *   add_filter( 'astra_child_seo_breadcrumbs_disable_astra_schema', '__return_false' );
 *
 * @package Astra Child
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tell Astra not to emit its own breadcrumb microdata / schema.
 *
 * Astra exposes a few filters depending on version. We hit all of them
 * defensively so the change is robust across Astra updates.
 */
function astra_child_seo_breadcrumbs_silence_astra() {
	if ( ! apply_filters( 'astra_child_seo_breadcrumbs_disable_astra_schema', true ) ) {
		return;
	}

	add_filter( 'astra_breadcrumb_disable_schema', '__return_true', 99 );
	add_filter( 'astra_breadcrumbs_disable_schema', '__return_true', 99 );
	add_filter( 'astra_breadcrumb_microdata', '__return_empty_string', 99 );
	add_filter( 'astra_breadcrumb_item_microdata', '__return_empty_string', 99 );
}
add_action( 'init', 'astra_child_seo_breadcrumbs_silence_astra' );

/**
 * Build a BreadcrumbList itemListElement array for the current request.
 *
 * Returns an empty array when no meaningful trail exists (e.g. front
 * page where Home is the page itself).
 *
 * @return array<int,array<string,mixed>>
 */
function astra_child_seo_breadcrumbs_items() {
	$items    = array();
	$position = 1;

	$items[] = array(
		'@type'    => 'ListItem',
		'position' => $position++,
		'name'     => __( 'Home', 'astra-child' ),
		'item'     => home_url( '/' ),
	);

	if ( is_front_page() ) {
		// The front page IS Home. A single-item BreadcrumbList is invalid
		// for Google, so signal an empty trail and let Yoast drop it.
		return array();
	}

	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			$primary = $cats[0];
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $primary->name,
				'item'     => get_category_link( $primary ),
			);
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => get_the_title(),
			'item'     => get_permalink(),
		);
	} elseif ( is_singular( 'page' ) ) {
		$ancestors = array_reverse( (array) get_post_ancestors( get_queried_object_id() ) );
		foreach ( $ancestors as $aid ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => get_the_title( $aid ),
				'item'     => get_permalink( $aid ),
			);
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => get_the_title(),
			'item'     => get_permalink(),
		);
	} elseif ( is_singular() ) {
		// Custom post type single.
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => get_the_title(),
			'item'     => get_permalink(),
		);
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && isset( $term->name, $term->term_id ) ) {
			$ancestors = get_ancestors( $term->term_id, $term->taxonomy );
			$ancestors = array_reverse( (array) $ancestors );
			foreach ( $ancestors as $tid ) {
				$ancestor = get_term( $tid, $term->taxonomy );
				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $position++,
						'name'     => $ancestor->name,
						'item'     => get_term_link( $ancestor ),
					);
				}
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $term->name,
				'item'     => get_term_link( $term ),
			);
		}
	} elseif ( is_search() ) {
		$query = get_search_query();
		if ( '' !== $query ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				/* translators: %s: search query */
				'name'     => sprintf( __( 'Search results for: %s', 'astra-child' ), $query ),
			);
		}
	} elseif ( is_author() ) {
		$author = get_queried_object();
		if ( $author && isset( $author->ID ) ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $author->display_name,
				'item'     => get_author_posts_url( $author->ID ),
			);
		}
	} elseif ( is_year() || is_month() || is_day() ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => wp_strip_all_tags( get_the_archive_title() ),
		);
	} elseif ( is_post_type_archive() ) {
		$pt = get_post_type_object( get_query_var( 'post_type' ) );
		if ( $pt ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $pt->labels->name,
				'item'     => get_post_type_archive_link( $pt->name ),
			);
		}
	} elseif ( is_404() ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => __( '404 Not Found', 'astra-child' ),
		);
	}

	if ( count( $items ) < 2 ) {
		// Single-item BreadcrumbList is invalid - report empty so caller
		// can drop the BreadcrumbList from the schema graph.
		return array();
	}

	/**
	 * Filter the rebuilt itemListElement before it replaces Yoast's value.
	 *
	 * @param array<int,array<string,mixed>> $items
	 */
	return (array) apply_filters( 'astra_child_seo_breadcrumb_items', $items );
}

/**
 * Make sure the BreadcrumbList Yoast emits has a valid itemListElement.
 *
 * @param array<string,mixed>|false $data Yoast schema piece data.
 * @return array<string,mixed>|false Modified piece, or false to drop the piece entirely.
 */
function astra_child_seo_breadcrumbs_filter_schema( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$has_items = ! empty( $data['itemListElement'] )
		&& is_array( $data['itemListElement'] )
		&& count( $data['itemListElement'] ) >= 2;

	if ( $has_items ) {
		return $data;
	}

	$rebuilt = astra_child_seo_breadcrumbs_items();

	if ( empty( $rebuilt ) ) {
		// No meaningful trail - tell Yoast to drop the piece. Returning
		// false from a wpseo_schema_* filter removes that node from the
		// JSON-LD graph.
		return false;
	}

	$data['@type']           = 'BreadcrumbList';
	$data['itemListElement'] = $rebuilt;

	return $data;
}
add_filter( 'wpseo_schema_breadcrumb', 'astra_child_seo_breadcrumbs_filter_schema', 99 );
