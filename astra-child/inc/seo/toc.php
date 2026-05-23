<?php
/**
 * Auto-generated Table of Contents for long posts.
 *
 * Builds a nested list of h2 / h3 headings from post content and either
 * appends it after the first paragraph automatically (for posts long
 * enough to warrant it) or replaces a [toc] shortcode marker for editors
 * who want to control placement.
 *
 * Side effect: heading tags get an `id` attribute so the TOC anchors
 * resolve. Existing IDs are preserved; auto-generated IDs use
 * sanitize_title() so Arabic headings produce clean Arabic slugs.
 *
 * Disable globally:
 *   add_filter( 'astra_child_seo_module_toc', '__return_false' );
 *
 * Disable per request:
 *   add_filter( 'astra_child_seo_toc_enabled', '__return_false' );
 *
 * @package Astra Child
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quick gate that filters out non-singular post requests.
 *
 * @return bool
 */
function astra_child_seo_toc_should_run() {
	if ( is_admin() || is_feed() ) {
		return false;
	}

	if ( ! is_singular( 'post' ) ) {
		return false;
	}

	if ( ! is_main_query() || ! in_the_loop() ) {
		return false;
	}

	/**
	 * Master toggle for the TOC module.
	 *
	 * @param bool $enabled
	 */
	return (bool) apply_filters( 'astra_child_seo_toc_enabled', true );
}

/**
 * Count words in a plain-text string with Unicode-aware splitting so
 * Arabic content gets a real count instead of zero.
 *
 * @param string $html Raw HTML.
 * @return int
 */
function astra_child_seo_toc_count_words( $html ) {
	$text = wp_strip_all_tags( (string) $html );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

	if ( '' === $text ) {
		return 0;
	}

	$parts = preg_split( '/\s+/u', $text );
	return is_array( $parts ) ? count( array_filter( $parts ) ) : 0;
}

/**
 * Parse content, ensure h2/h3 headings have IDs, and collect a list of
 * headings for the TOC.
 *
 * @param string $content Post HTML.
 * @return array{0:string,1:array<int,array{id:string,level:int,text:string}>}
 *         Tuple of (modified content, headings list).
 */
function astra_child_seo_toc_collect( $content ) {
	$levels = (array) apply_filters( 'astra_child_seo_toc_levels', array( 'h2', 'h3' ) );
	$levels = array_map( 'strtolower', $levels );

	if ( empty( $levels ) ) {
		return array( $content, array() );
	}

	$dom         = new DOMDocument( '1.0', 'UTF-8' );
	$prev_libxml = libxml_use_internal_errors( true );

	$wrapper = '<?xml encoding="UTF-8"?><div id="ac-toc-root">' . $content . '</div>';
	$loaded  = $dom->loadHTML( $wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

	libxml_clear_errors();
	libxml_use_internal_errors( $prev_libxml );

	if ( ! $loaded ) {
		return array( $content, array() );
	}

	$root = $dom->getElementById( 'ac-toc-root' );
	if ( ! $root ) {
		return array( $content, array() );
	}

	$xpath      = new DOMXPath( $dom );
	$conditions = array_map(
		static function ( $tag ) {
			return "name()='" . $tag . "'";
		},
		$levels
	);
	$query      = '//*[' . implode( ' or ', $conditions ) . ']';

	$nodes = $xpath->query( $query, $root );
	if ( ! $nodes || 0 === $nodes->length ) {
		return array( $content, array() );
	}

	$headings = array();
	$used_ids = array();
	$counter  = 0;

	foreach ( $nodes as $node ) {
		$text = trim( (string) $node->textContent );
		if ( '' === $text ) {
			continue;
		}

		$id = (string) $node->getAttribute( 'id' );
		if ( '' === $id ) {
			$base = sanitize_title( $text );
			if ( '' === $base ) {
				$base = 'section-' . ( ++$counter );
			}

			$candidate = $base;
			$dup       = 2;
			while ( in_array( $candidate, $used_ids, true ) ) {
				$candidate = $base . '-' . $dup;
				++$dup;
			}
			$id = $candidate;
			$node->setAttribute( 'id', $id );
		}

		$used_ids[] = $id;
		$headings[] = array(
			'id'    => $id,
			'level' => (int) substr( $node->nodeName, 1 ),
			'text'  => $text,
		);
	}

	$modified = '';
	foreach ( $root->childNodes as $child ) {
		$modified .= $dom->saveHTML( $child );
	}

	return array( '' !== $modified ? $modified : $content, $headings );
}

/**
 * Render the TOC HTML from a list of heading entries.
 *
 * Builds a nested ordered list driven by the heading levels so h3s end
 * up indented under their preceding h2.
 *
 * @param array<int,array{id:string,level:int,text:string}> $headings Heading list.
 * @return string
 */
function astra_child_seo_toc_render( $headings ) {
	if ( empty( $headings ) ) {
		return '';
	}

	$title = (string) apply_filters(
		'astra_child_seo_toc_title',
		__( 'Contents', 'astra-child' )
	);

	$collapsible = (bool) apply_filters( 'astra_child_seo_toc_collapsible', true );

	$min_level = min( wp_list_pluck( $headings, 'level' ) );

	ob_start();

	if ( $collapsible ) {
		echo '<details class="ac-toc" open>';
		echo '<summary class="ac-toc-title">' . esc_html( $title ) . '</summary>';
	} else {
		echo '<nav class="ac-toc" aria-label="' . esc_attr( $title ) . '">';
		echo '<h2 class="ac-toc-title">' . esc_html( $title ) . '</h2>';
	}

	echo '<ol class="ac-toc-list">';

	$prev_level = $min_level;
	foreach ( $headings as $i => $h ) {
		$level = (int) $h['level'];

		if ( 0 === $i ) {
			// First item.
		} elseif ( $level > $prev_level ) {
			echo str_repeat( '<ol class="ac-toc-sublist">', $level - $prev_level );
		} elseif ( $level < $prev_level ) {
			echo '</li>';
			echo str_repeat( '</ol></li>', $prev_level - $level );
		} else {
			echo '</li>';
		}

		printf(
			'<li class="ac-toc-item"><a href="#%s">%s</a>',
			esc_attr( $h['id'] ),
			esc_html( $h['text'] )
		);

		$prev_level = $level;
	}

	echo '</li>';
	echo str_repeat( '</ol></li>', $prev_level - $min_level );
	echo '</ol>';

	if ( $collapsible ) {
		echo '</details>';
	} else {
		echo '</nav>';
	}

	return (string) ob_get_clean();
}

/**
 * Insert TOC HTML after the first closing </p> in the content.
 *
 * Falls back to prepending if no paragraph is found.
 *
 * @param string $content  Original content.
 * @param string $toc_html TOC HTML to inject.
 * @return string
 */
function astra_child_seo_toc_insert_after_first_paragraph( $content, $toc_html ) {
	$replaced = preg_replace(
		'/(<\/p>)/u',
		'$1' . "\n" . $toc_html,
		$content,
		1,
		$count
	);

	if ( 0 === $count || null === $replaced ) {
		return $toc_html . "\n" . $content;
	}

	return (string) $replaced;
}

/**
 * Main filter that orchestrates ID injection, TOC building, and placement.
 *
 * @param string $content Post HTML.
 * @return string
 */
function astra_child_seo_toc_filter( $content ) {
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return $content;
	}

	if ( ! astra_child_seo_toc_should_run() ) {
		return $content;
	}

	$has_marker = ( false !== strpos( $content, '<!--ac-toc-placeholder-->' ) );

	$min_words = (int) apply_filters( 'astra_child_seo_toc_min_words', 500 );
	if ( ! $has_marker && astra_child_seo_toc_count_words( $content ) < $min_words ) {
		return $content;
	}

	list( $modified, $headings ) = astra_child_seo_toc_collect( $content );

	$min_headings = (int) apply_filters( 'astra_child_seo_toc_min_headings', 3 );

	if ( count( $headings ) < $min_headings ) {
		// Not enough headings: keep modified content (with new IDs) but
		// don't render a TOC. Manual marker, if present, is removed
		// rather than left dangling.
		if ( $has_marker ) {
			$modified = str_replace( '<!--ac-toc-placeholder-->', '', $modified );
		}
		return $modified;
	}

	$toc_html = astra_child_seo_toc_render( $headings );
	if ( '' === $toc_html ) {
		return $modified;
	}

	if ( $has_marker ) {
		return str_replace( '<!--ac-toc-placeholder-->', $toc_html, $modified );
	}

	if ( ! apply_filters( 'astra_child_seo_toc_auto_insert', true ) ) {
		return $modified;
	}

	return astra_child_seo_toc_insert_after_first_paragraph( $modified, $toc_html );
}
add_filter( 'the_content', 'astra_child_seo_toc_filter', 11 );

/**
 * `[toc]` shortcode. Outputs an HTML comment marker that the_content
 * filter replaces with the rendered TOC. Using a comment marker avoids
 * double-processing the content and lets the same code path handle both
 * automatic and manual placement.
 *
 * @return string
 */
function astra_child_seo_toc_shortcode() {
	return '<!--ac-toc-placeholder-->';
}
add_shortcode( 'toc', 'astra_child_seo_toc_shortcode' );

/**
 * Inline minimal styles for the TOC.
 */
function astra_child_seo_toc_styles() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	$scroll_offset = (int) apply_filters( 'astra_child_seo_toc_scroll_offset', 80 );

	$css = sprintf(
		'html{scroll-behavior:smooth;scroll-padding-top:%dpx}'
		. '.ac-toc{margin:2em 0;padding:1.25em 1.5em;background:#f7f8fa;border:1px solid #e5e7eb;border-radius:8px;font-size:.95rem}'
		. '.ac-toc-title{font-weight:700;font-size:1.05rem;cursor:pointer;list-style:none;display:flex;align-items:center;gap:.5em;margin:0}'
		. 'details.ac-toc>.ac-toc-title::-webkit-details-marker{display:none}'
		. 'details.ac-toc>.ac-toc-title::after{content:"-";margin-inline-start:auto;font-weight:400;font-size:1.4em;line-height:1}'
		. 'details.ac-toc:not([open])>.ac-toc-title::after{content:"+"}'
		. '.ac-toc-list{list-style-type:decimal;margin:.75em 0 0;padding-inline-start:1.75em;line-height:1.85}'
		. '.ac-toc-sublist{list-style-type:lower-arabic;margin:.25em 0;padding-inline-start:1.5em}'
		. '.ac-toc-item>a{color:inherit;text-decoration:none;border-bottom:1px dotted transparent;transition:border-color .15s ease,color .15s ease}'
		. '.ac-toc-item>a:hover,.ac-toc-item>a:focus{color:#0274be;border-bottom-color:currentColor;text-decoration:none}',
		$scroll_offset
	);

	printf( '<style id="ac-toc-styles">%s</style>', $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'astra_child_seo_toc_styles', 99 );
