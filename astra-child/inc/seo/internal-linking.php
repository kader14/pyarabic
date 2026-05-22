<?php
/**
 * Internal linking automation.
 *
 * Two complementary features:
 *
 *   1. Keyword auto-linking. Site admin maintains a list of
 *      "keyword | URL" pairs in Settings -> Internal Links. The first
 *      occurrence of each keyword in any singular post is converted
 *      into an internal link, skipping content inside <a>, <code>,
 *      <pre>, headings, scripts, styles, and forms so we never break
 *      existing markup. Self-links and overuse are avoided.
 *
 *   2. Related-posts block appended to single-post output. Posts are
 *      picked by shared tags or categories, ranked by recency, cached
 *      per post via transients, and rendered as an accessible <aside>.
 *      A `[related_posts]` shortcode is also registered for manual
 *      placement; if used inside the_content, the automatic append is
 *      suppressed for that post so it isn't duplicated.
 *
 * @package Astra Child
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 *  Settings: Settings -> Internal Links
 * ------------------------------------------------------------------------- */

/**
 * Register the settings page entry under Settings.
 */
function astra_child_il_admin_menu() {
	add_options_page(
		__( 'Internal Links', 'astra-child' ),
		__( 'Internal Links', 'astra-child' ),
		'manage_options',
		'astra-child-internal-links',
		'astra_child_il_render_settings_page'
	);
}
add_action( 'admin_menu', 'astra_child_il_admin_menu' );

/**
 * Register the textarea option that stores the link map.
 */
function astra_child_il_register_setting() {
	register_setting(
		'astra_child_il_group',
		'astra_child_internal_links_raw',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'astra_child_il_sanitize_raw',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'astra_child_il_register_setting' );

/**
 * Light sanitization for the raw textarea content.
 *
 * @param string $raw Raw value from the form.
 * @return string
 */
function astra_child_il_sanitize_raw( $raw ) {
	if ( ! is_string( $raw ) ) {
		return '';
	}
	$raw = wp_check_invalid_utf8( $raw );
	return wp_unslash( $raw );
}

/**
 * Render the settings page.
 */
function astra_child_il_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Internal Links', 'astra-child' ); ?></h1>
		<p>
			<?php esc_html_e( 'Define keyword-to-URL pairs, one per line, separated by a pipe character. The first occurrence of each keyword in any post will be auto-linked.', 'astra-child' ); ?>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'astra_child_il_group' ); ?>
			<textarea
				name="astra_child_internal_links_raw"
				rows="18"
				class="large-text code"
				dir="auto"
				placeholder="WordPress | https://pyarabic.com/wordpress-guide"
			><?php echo esc_textarea( (string) get_option( 'astra_child_internal_links_raw', '' ) ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Format examples:', 'astra-child' ); ?><br>
				<code>WordPress | https://pyarabic.com/wordpress-guide</code><br>
				<code>&#1576;&#1575;&#1610;&#1579;&#1608;&#1606; | https://pyarabic.com/python-intro</code><br>
				<code>Yoast SEO | https://pyarabic.com/yoast-seo-tutorial</code>
			</p>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 *  Auto-link keywords inside post content
 * ------------------------------------------------------------------------- */

/**
 * Parse the saved option into an associative array of keyword => URL.
 *
 * Cached per request.
 *
 * @return array<string,string>
 */
function astra_child_il_get_link_map() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$raw   = (string) get_option( 'astra_child_internal_links_raw', '' );
	$lines = preg_split( '/\R/u', $raw );
	$map   = array();

	foreach ( (array) $lines as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line || false === strpos( $line, '|' ) ) {
			continue;
		}

		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( count( $parts ) !== 2 ) {
			continue;
		}

		list( $keyword, $url ) = $parts;
		if ( '' === $keyword || '' === $url ) {
			continue;
		}

		$map[ $keyword ] = esc_url_raw( $url );
	}

	/**
	 * Filter the parsed keyword map.
	 *
	 * @param array<string,string> $map
	 */
	$cache = (array) apply_filters( 'astra_child_il_link_map', $map );
	return $cache;
}

/**
 * Walk the post content and auto-link keywords using DOMDocument so
 * we never inject inside protected nodes.
 *
 * @param string $content Post HTML content.
 * @return string
 */
function astra_child_il_auto_link( $content ) {
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return $content;
	}

	if ( is_admin() || is_feed() || ! is_singular() ) {
		return $content;
	}

	$map = astra_child_il_get_link_map();
	if ( empty( $map ) ) {
		return $content;
	}

	$current_url = trailingslashit( (string) get_permalink() );
	$max_links   = (int) apply_filters( 'astra_child_il_max_links_per_post', 5 );

	if ( $max_links <= 0 ) {
		return $content;
	}

	$dom         = new DOMDocument( '1.0', 'UTF-8' );
	$prev_libxml = libxml_use_internal_errors( true );

	$wrapper = '<?xml encoding="UTF-8"?><div id="ac-il-root">' . $content . '</div>';

	$loaded = $dom->loadHTML( $wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev_libxml );

	if ( ! $loaded ) {
		return $content;
	}

	$root = $dom->getElementById( 'ac-il-root' );
	if ( ! $root ) {
		return $content;
	}

	$xpath = new DOMXPath( $dom );

	$skipped_ancestors = array(
		'a',
		'code',
		'pre',
		'kbd',
		'samp',
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		'script',
		'style',
		'input',
		'textarea',
		'button',
		'svg',
	);

	$skip_clauses = array();
	foreach ( $skipped_ancestors as $tag ) {
		$skip_clauses[] = "not(ancestor::{$tag})";
	}
	$query = '//text()[' . implode( ' and ', $skip_clauses ) . ']';

	$nodes = $xpath->query( $query, $root );
	if ( ! $nodes ) {
		return $content;
	}

	$linked_keywords = array();
	$linked_count    = 0;

	foreach ( $nodes as $node ) {
		if ( $linked_count >= $max_links ) {
			break;
		}

		$text = $node->nodeValue;
		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			continue;
		}

		foreach ( $map as $keyword => $url ) {
			if ( in_array( $keyword, $linked_keywords, true ) ) {
				continue;
			}

			if ( trailingslashit( $url ) === $current_url ) {
				continue;
			}

			$pos = mb_strpos( $text, $keyword, 0, 'UTF-8' );
			if ( false === $pos ) {
				continue;
			}

			$before  = mb_substr( $text, 0, $pos, 'UTF-8' );
			$matched = mb_substr( $text, $pos, mb_strlen( $keyword, 'UTF-8' ), 'UTF-8' );
			$after   = mb_substr( $text, $pos + mb_strlen( $keyword, 'UTF-8' ), null, 'UTF-8' );

			$link = $dom->createElement( 'a' );
			$link->setAttribute( 'href', $url );
			$link->setAttribute( 'class', 'ac-internal-link' );
			$link->setAttribute( 'rel', 'bookmark' );
			$link->appendChild( $dom->createTextNode( $matched ) );

			$parent = $node->parentNode;
			if ( ! $parent ) {
				break;
			}

			if ( '' !== $before ) {
				$parent->insertBefore( $dom->createTextNode( $before ), $node );
			}
			$parent->insertBefore( $link, $node );
			if ( '' !== $after ) {
				$parent->insertBefore( $dom->createTextNode( $after ), $node );
			}
			$parent->removeChild( $node );

			$linked_keywords[] = $keyword;
			++$linked_count;
			break; // process at most one keyword per text node
		}
	}

	$output = '';
	foreach ( $root->childNodes as $child ) {
		$output .= $dom->saveHTML( $child );
	}

	return '' !== $output ? $output : $content;
}
add_filter( 'the_content', 'astra_child_il_auto_link', 20 );

/* -------------------------------------------------------------------------
 *  Related posts
 * ------------------------------------------------------------------------- */

/**
 * Fetch related posts for a given post id.
 *
 * Cached per post via transient. Cache busts on save_post for the post.
 *
 * @param int $post_id Source post id.
 * @param int $count   How many related posts to return.
 * @return WP_Post[]
 */
function astra_child_il_get_related_posts( $post_id, $count = 4 ) {
	$post_id = (int) $post_id;
	$count   = max( 1, (int) $count );

	$cache_key = 'ac_il_related_' . $post_id . '_' . $count;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) {
		return array_filter( array_map( 'get_post', $cached ) );
	}

	$tag_ids = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );
	$cat_ids = wp_get_post_categories( $post_id );

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
	);

	$tax_query = array();
	if ( ! empty( $tag_ids ) ) {
		$tax_query[] = array(
			'taxonomy' => 'post_tag',
			'field'    => 'term_id',
			'terms'    => $tag_ids,
		);
	}
	if ( ! empty( $cat_ids ) ) {
		$tax_query[] = array(
			'taxonomy' => 'category',
			'field'    => 'term_id',
			'terms'    => $cat_ids,
		);
	}

	if ( ! empty( $tax_query ) ) {
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'OR';
		}
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	/**
	 * Filter the WP_Query args used to look up related posts.
	 *
	 * @param array $args
	 * @param int   $post_id
	 */
	$args = (array) apply_filters( 'astra_child_il_related_query_args', $args, $post_id );

	$posts = get_posts( $args );

	if ( count( $posts ) < $count ) {
		// Pad with latest posts so the block always feels populated.
		$pad_args  = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $count - count( $posts ),
			'post__not_in'        => array_merge( array( $post_id ), wp_list_pluck( $posts, 'ID' ) ),
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
		);
		$pad_posts = get_posts( $pad_args );
		$posts     = array_merge( $posts, $pad_posts );
	}

	$ids = wp_list_pluck( $posts, 'ID' );
	set_transient( $cache_key, $ids, HOUR_IN_SECONDS );

	return $posts;
}

/**
 * Bust the related-posts cache when a post is saved.
 *
 * @param int $post_id Saved post id.
 */
function astra_child_il_bust_cache( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	// Bust common variants. Other counts age out naturally.
	delete_transient( 'ac_il_related_' . $post_id . '_4' );
	delete_transient( 'ac_il_related_' . $post_id . '_3' );
	delete_transient( 'ac_il_related_' . $post_id . '_6' );
}
add_action( 'save_post', 'astra_child_il_bust_cache' );
add_action( 'deleted_post', 'astra_child_il_bust_cache' );

/**
 * Render the related-posts block as an HTML string.
 *
 * @param array{count?:int,title?:string,post_id?:int} $args Options.
 * @return string
 */
function astra_child_il_render_related_posts( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'count'   => 4,
			'title'   => '',
			'post_id' => get_the_ID(),
		)
	);

	$post_id = (int) $args['post_id'];
	if ( $post_id <= 0 ) {
		return '';
	}

	$count = max( 1, (int) $args['count'] );
	$title = (string) $args['title'];
	if ( '' === $title ) {
		$title = (string) apply_filters(
			'astra_child_il_related_title',
			__( 'Related articles', 'astra-child' )
		);
	}

	$posts = astra_child_il_get_related_posts( $post_id, $count );
	if ( empty( $posts ) ) {
		return '';
	}

	ob_start();
	?>
	<aside class="ac-related-posts" aria-labelledby="ac-related-heading">
		<h2 id="ac-related-heading" class="ac-related-title"><?php echo esc_html( $title ); ?></h2>
		<ul class="ac-related-list">
			<?php foreach ( $posts as $related ) : ?>
				<li class="ac-related-item">
					<a class="ac-related-link" href="<?php echo esc_url( get_permalink( $related ) ); ?>" rel="bookmark">
						<?php if ( has_post_thumbnail( $related ) ) : ?>
							<span class="ac-related-thumb">
								<?php
								echo get_the_post_thumbnail(
									$related,
									'medium',
									array(
										'loading' => 'lazy',
										'alt'     => esc_attr( get_the_title( $related ) ),
									)
								);
								?>
							</span>
						<?php endif; ?>
						<span class="ac-related-info">
							<span class="ac-related-heading"><?php echo esc_html( get_the_title( $related ) ); ?></span>
							<span class="ac-related-date"><?php echo esc_html( get_the_date( '', $related ) ); ?></span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</aside>
	<?php
	return (string) ob_get_clean();
}

/**
 * Append the related-posts block to single-post the_content output.
 *
 * Skipped if the current content already contains the [related_posts]
 * shortcode, so manual placement wins.
 *
 * @param string $content Post HTML content.
 * @return string
 */
function astra_child_il_append_related_posts( $content ) {
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return $content;
	}

	if ( is_admin() || is_feed() || ! is_singular( 'post' ) ) {
		return $content;
	}

	if ( ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}

	if ( ! apply_filters( 'astra_child_il_auto_append_related', true ) ) {
		return $content;
	}

	if ( false !== strpos( $content, '[related_posts' ) || has_shortcode( $content, 'related_posts' ) ) {
		return $content;
	}

	$count = (int) apply_filters( 'astra_child_il_related_count', 4 );

	$block = astra_child_il_render_related_posts(
		array(
			'count'   => $count,
			'post_id' => get_the_ID(),
		)
	);

	if ( '' === $block ) {
		return $content;
	}

	return $content . "\n" . $block;
}
add_filter( 'the_content', 'astra_child_il_append_related_posts', 25 );

/* -------------------------------------------------------------------------
 *  Shortcode for manual placement: [related_posts count="4" title="..."]
 * ------------------------------------------------------------------------- */

/**
 * Shortcode handler.
 *
 * @param array<string,string>|string $atts Shortcode attributes.
 * @return string
 */
function astra_child_il_related_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'count' => 4,
			'title' => '',
		),
		(array) $atts,
		'related_posts'
	);

	return astra_child_il_render_related_posts(
		array(
			'count'   => (int) $atts['count'],
			'title'   => (string) $atts['title'],
			'post_id' => get_the_ID(),
		)
	);
}
add_shortcode( 'related_posts', 'astra_child_il_related_shortcode' );

/* -------------------------------------------------------------------------
 *  Inline minimal styles (no extra HTTP request)
 * ------------------------------------------------------------------------- */

/**
 * Print scoped CSS for the related-posts block once per page.
 */
function astra_child_il_print_styles() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	$css = '.ac-related-posts{margin:2.5em 0;padding-top:1.5em;border-top:1px solid #e5e5e5}'
		. '.ac-related-title{font-size:1.25rem;margin:0 0 1em;font-weight:700}'
		. '.ac-related-list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1em}'
		. '.ac-related-item{margin:0}'
		. '.ac-related-link{display:flex;flex-direction:column;text-decoration:none;color:inherit;border:1px solid #eee;border-radius:6px;overflow:hidden;background:#fff;transition:transform .15s ease,box-shadow .15s ease}'
		. '.ac-related-link:hover,.ac-related-link:focus{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.06);text-decoration:none}'
		. '.ac-related-thumb img{width:100%;height:auto;aspect-ratio:16/9;object-fit:cover;display:block}'
		. '.ac-related-info{padding:.75em 1em;display:flex;flex-direction:column;gap:.25em}'
		. '.ac-related-heading{font-weight:600;line-height:1.4}'
		. '.ac-related-date{font-size:.85rem;color:#777}'
		. '.ac-internal-link{border-bottom:1px dotted currentColor}';

	printf( '<style id="ac-internal-linking">%s</style>', $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'astra_child_il_print_styles', 99 );
