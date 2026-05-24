<?php
/**
 * Breadcrumb schema repair.
 *
 * Fixes the Search Console error
 *
 *     Missing field "itemListElement" (in "BreadcrumbList")
 *
 * which Yoast SEO Free emits when a page references a BreadcrumbList that
 * doesn't actually exist in the @graph (orphan reference). This happens
 * when Yoast emits `WebPage.breadcrumb = {@id: ...#breadcrumb}` but the
 * BreadcrumbList graph piece is missing - either because Yoast itself
 * suppressed it, or because a downstream filter dropped it.
 *
 * Three-pronged fix:
 *
 *   1. Suppress Astra's microdata so we keep a single, validated source
 *      of structured data.
 *
 *   2. Filter Yoast's wpseo_schema_breadcrumb piece. If itemListElement
 *      is missing or empty, rebuild it from the current request.
 *
 *   3. Final pass on wpseo_schema_graph: if WebPage references a
 *      BreadcrumbList @id that has no matching node, either inject a
 *      valid node we built ourselves, or strip the dangling reference
 *      so Google doesn't see an empty BreadcrumbList stub.
 *
 * @package Astra Child
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tell Astra not to emit its own breadcrumb microdata / schema.
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
 * @return array<string,mixed>|false
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
		// No meaningful trail. The graph filter below will strip the
		// orphan WebPage.breadcrumb reference.
		return false;
	}

	$data['@type']           = 'BreadcrumbList';
	$data['itemListElement'] = $rebuilt;

	return $data;
}
add_filter( 'wpseo_schema_breadcrumb', 'astra_child_seo_breadcrumbs_filter_schema', 99 );

/**
 * Final-pass guarantee: ensure WebPage's breadcrumb reference resolves to
 * an actual BreadcrumbList node in the @graph.
 *
 * Yoast Free in some configurations (notably with Astra as parent theme,
 * Yoast 21+) emits `WebPage.breadcrumb = {@id: ".../#breadcrumb"}` but
 * never adds the BreadcrumbList graph piece itself. Our wpseo_schema_breadcrumb
 * filter only fires when Yoast generates the piece in the first place, so
 * it can't fix this case.
 *
 * This filter walks the final graph after every other Yoast piece has been
 * generated. If the page references a BreadcrumbList @id that doesn't have
 * a matching node, we inject a valid one ourselves. If we can't build a
 * meaningful trail, we strip the dangling reference instead so Google
 * doesn't synthesize an empty BreadcrumbList from the orphan.
 *
 * @param array<int,array<string,mixed>> $graph   The full @graph array.
 * @param mixed                          $context Yoast meta_tags_context.
 * @return array<int,array<string,mixed>>
 */
function astra_child_seo_breadcrumbs_ensure_node( $graph, $context = null ) {
	unset( $context );

	if ( ! is_array( $graph ) || empty( $graph ) ) {
		return $graph;
	}

	$page_types = array(
		'WebPage',
		'CollectionPage',
		'ItemPage',
		'AboutPage',
		'ContactPage',
		'ProfilePage',
		'SearchResultsPage',
	);

	// Locate the WebPage piece and the @id it expects for its breadcrumb.
	$referenced_id = null;
	$owner_idx     = null;

	foreach ( $graph as $idx => $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			continue;
		}
		$types     = (array) $node['@type'];
		$is_page   = (bool) array_intersect( $types, $page_types );
		if ( ! $is_page ) {
			continue;
		}
		if ( ! empty( $node['breadcrumb']['@id'] ) ) {
			$referenced_id = $node['breadcrumb']['@id'];
			$owner_idx     = $idx;
			break;
		}
	}

	if ( null === $referenced_id ) {
		return $graph;
	}

	// Already has a real BreadcrumbList node? Leave the graph alone.
	foreach ( $graph as $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) || empty( $node['@id'] ) ) {
			continue;
		}
		$types = (array) $node['@type'];
		if ( in_array( 'BreadcrumbList', $types, true ) && $node['@id'] === $referenced_id ) {
			return $graph;
		}
	}

	// Orphan reference. Inject a real BreadcrumbList or strip the reference.
	$items = astra_child_seo_breadcrumbs_items();

	if ( count( $items ) < 2 ) {
		unset( $graph[ $owner_idx ]['breadcrumb'] );
		return array_values( $graph );
	}

	$graph[] = array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $referenced_id,
		'itemListElement' => $items,
	);

	return $graph;
}
add_filter( 'wpseo_schema_graph', 'astra_child_seo_breadcrumbs_ensure_node', 100, 2 );
