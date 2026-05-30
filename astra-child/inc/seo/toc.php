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
 * --------------------------------------------------------------------
 * Excluding specific headings from the TOC
 * --------------------------------------------------------------------
 *
 * 1. Per-heading opt-out:
 *      <h2 class="no-toc">Skip me</h2>
 *      <h2 data-toc="false">Skip me too</h2>
 *
 * 2. Container opt-out (excludes every heading inside):
 *      <div class="no-toc">
 *          <h2>...</h2>
 *          <h3>...</h3>
 *      </div>
 *
 * 3. Headings inside these semantic containers are skipped automatically:
 *      <aside>, <blockquote>, <nav>, <header>, <footer>,
 *      <form>, <details>, <figure>, <figcaption>, <template>
 *
 *    Override the list:
 *      add_filter( 'astra_child_seo_toc_excluded_containers', function ( $tags ) {
 *          $tags[] = 'section';     // also skip headings inside <section>
 *          return array_diff( $tags, array( 'aside' ) ); // ... but allow aside
 *      } );
 *
 * 4. Skip headings whose text matches a regex pattern. Useful when a
 *    related-posts block injects a fixed heading like "اقرأ أيضاً":
 *
 *      add_filter( 'astra_child_seo_toc_excluded_text_patterns', function ( $patterns ) {
 *          $patterns[] = '/^اقرأ\s+أيضا/u';
 *          $patterns[] = '/^مقالات\s+ذات\s+صلة/u';
 *          $patterns[] = '/^You may also like/i';
 *          return $patterns;
 *      } );
 *
 * --------------------------------------------------------------------
 * Other tunables
 * --------------------------------------------------------------------
 *
 *  - Post types that get a TOC (default: ['post']):
 *      astra_child_seo_toc_post_types
 *  - Heading levels considered (default: ['h2','h3']):
 *      astra_child_seo_toc_levels
 *  - Minimum word count to auto-insert (default: 500):
 *      astra_child_seo_toc_min_words
 *  - Minimum heading count required to render (default: 3):
 *      astra_child_seo_toc_min_headings
 *  - Maximum heading count - extras truncated (default: 30, 0 = unlimited):
 *      astra_child_seo_toc_max_headings
 *  - Disable per-request:        astra_child_seo_toc_enabled
 *  - Disable globally (module):  astra_child_seo_module_toc
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

	$post_types = (array) apply_filters( 'astra_child_seo_toc_post_types', array( 'post' ) );

	if ( ! is_singular( $post_types ) ) {
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
 * Default list of structural elements whose headings should not appear
 * in the TOC. These are wrappers for tangentially related content
 * (asides, quotes, navigation, related-posts blocks, etc.) where the
 * heading is part of the wrapper's UI rather than the article outline.
 *
 * @return array<int,string>
 */
function astra_child_seo_toc_default_excluded_containers() {
	return array(
		'aside',
		'blockquote',
		'nav',
		'header',
		'footer',
		'form',
		'details',     // collapsibles - already self-document
		'figure',
		'figcaption',
		'template',    // <template> contents shouldn't render at all
	);
}

/**
 * Decide whether a heading element should be excluded from the TOC.
 *
 * Excluded when:
 *   - the heading itself or any ancestor up to (but not including) $root
 *     has class "no-toc";
 *   - the heading has data-toc="false";
 *   - any ancestor up to $root is in $excluded_containers;
 *   - the heading text matches one of $excluded_text_patterns (regex).
 *
 * @param DOMElement                  $node                    Heading element.
 * @param DOMNode                     $root                    Wrapper element to stop ancestor walk at.
 * @param array<int,string>           $excluded_containers     Lower-cased tag names.
 * @param array<int,string>           $excluded_text_patterns  Regex patterns (PCRE).
 * @return bool
 */
function astra_child_seo_toc_is_excluded( $node, $root, $excluded_containers, $excluded_text_patterns ) {
	// Per-heading data-toc opt-out.
	$data_toc = strtolower( trim( (string) $node->getAttribute( 'data-toc' ) ) );
	if ( 'false' === $data_toc || 'no' === $data_toc || '0' === $data_toc ) {
		return true;
	}

	// Walk ancestors up to (but not including) the wrapper root.
	for ( $ancestor = $node; $ancestor && $ancestor !== $root; $ancestor = $ancestor->parentNode ) {
		if ( ! ( $ancestor instanceof DOMElement ) ) {
			continue;
		}

		$tag = strtolower( $ancestor->nodeName );

		if ( in_array( $tag, $excluded_containers, true ) ) {
			return true;
		}

		$class_attr = (string) $ancestor->getAttribute( 'class' );
		if ( '' !== $class_attr ) {
			$classes = preg_split( '/\s+/', $class_attr ) ?: array();
			if ( in_array( 'no-toc', $classes, true ) ) {
				return true;
			}
		}
	}

	// Text-pattern exclusion (regex).
	if ( ! empty( $excluded_text_patterns ) ) {
		$text = trim( (string) $node->textContent );
		foreach ( $excluded_text_patterns as $pattern ) {
			if ( ! is_string( $pattern ) || '' === $pattern ) {
				continue;
			}
			$matched = @preg_match( $pattern, $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( 1 === $matched ) {
				return true;
			}
		}
	}

	return false;
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
	$levels = array_unique( array_map( 'strtolower', array_filter( $levels, 'is_string' ) ) );

	if ( empty( $levels ) ) {
		return array( $content, array() );
	}

	$excluded_containers = (array) apply_filters(
		'astra_child_seo_toc_excluded_containers',
		astra_child_seo_toc_default_excluded_containers()
	);
	$excluded_containers = array_map( 'strtolower', array_filter( $excluded_containers, 'is_string' ) );

	$excluded_text_patterns = (array) apply_filters(
		'astra_child_seo_toc_excluded_text_patterns',
		array()
	);

	$dom         = new DOMDocument( '1.0', 'UTF-8' );
	$prev_libxml = libxml_use_internal_errors( true );

	$wrapper = '<?xml encoding="UTF-8"?><div id="ac-toc-root">' . $content . '</div>';
	$loaded  = $dom->loadHTML( $wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

	libxml_clear_errors();
	libxml_use_internal_errors( $prev_libxml );

	if ( ! $loaded ) {
		return array( $content, array() );
	}

	// Without a DTD, DOMDocument::getElementById is unreliable - fall
	// back to an XPath @id lookup, which works regardless of DTD state.
	$xpath      = new DOMXPath( $dom );
	$root_nodes = $xpath->query( "//*[@id='ac-toc-root']" );
	$root       = ( $root_nodes && $root_nodes->length ) ? $root_nodes->item( 0 ) : null;

	if ( ! $root instanceof DOMElement ) {
		return array( $content, array() );
	}

	// Build a relative XPath that matches the configured heading levels
	// only as descendants of the wrapper. The leading `.//` is essential
	// here - `//` alone would search the whole document, which only
	// happens to work because the wrapper is the document root, but the
	// relative form keeps semantics honest and survives future refactors.
	$conditions = array_map(
		static function ( $tag ) {
			return 'self::' . $tag;
		},
		$levels
	);
	$query = './/*[' . implode( ' or ', $conditions ) . ']';

	$nodes = $xpath->query( $query, $root );
	if ( ! $nodes || 0 === $nodes->length ) {
		return array( $content, array() );
	}

	$headings = array();
	$used_ids = array();
	$counter  = 0;

	foreach ( $nodes as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}

		if ( astra_child_seo_toc_is_excluded( $node, $root, $excluded_containers, $excluded_text_patterns ) ) {
			continue;
		}

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
 * up indented under their preceding h2. Handles the case where the
 * first heading is deeper than the minimum level (e.g. a post that
 * starts with an h3 intro before its h2 sections) by opening the
 * required sub-lists up front instead of leaving the h3 dangling at
 * the top level.
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
			// First item: if it's deeper than the minimum, open the
			// required number of sub-lists so the first <li> sits at
			// the correct depth instead of dangling at top level.
			if ( $level > $min_level ) {
				echo str_repeat( '<ol class="ac-toc-sublist">', $level - $min_level );
			}
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
	if ( $prev_level > $min_level ) {
		echo str_repeat( '</ol></li>', $prev_level - $min_level );
	}
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

	// Cap runaway TOCs - extras dropped silently. 0 disables the cap.
	$max_headings = (int) apply_filters( 'astra_child_seo_toc_max_headings', 30 );
	if ( $max_headings > 0 && count( $headings ) > $max_headings ) {
		$headings = array_slice( $headings, 0, $max_headings );
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
