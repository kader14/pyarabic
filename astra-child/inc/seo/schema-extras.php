<?php
/**
 * Extra structured data that Yoast SEO Free does not emit on its own.
 *
 * Currently provides:
 *   - [faq] / [faq_item] shortcodes that render an accessible accordion
 *     and emit FAQPage JSON-LD so the entries are eligible for rich
 *     results in Google search.
 *
 * Usage:
 *   [faq]
 *     [faq_item q="ما هو بايثون؟"]بايثون لغة برمجة عالية المستوى ...[/faq_item]
 *     [faq_item q="كيف أبدأ؟"]ابدأ بتثبيت بايثون من python.org ...[/faq_item]
 *   [/faq]
 *
 * @package Astra Child
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-request collector for [faq_item] entries inside a [faq] wrapper.
 *
 * Reset at the start of each [faq] expansion.
 *
 * @return array<int,array{q:string,a:string}>
 */
function &astra_child_seo_faq_buffer() {
	static $buffer = array();
	return $buffer;
}

/**
 * Inner [faq_item] shortcode. Captures one Q/A pair into the buffer and
 * returns the rendered accordion item HTML.
 *
 * @param array<string,string>|string $atts    Shortcode attributes.
 * @param string|null                 $content Inner content (the answer).
 * @return string
 */
function astra_child_seo_shortcode_faq_item( $atts, $content = null ) {
	$atts = shortcode_atts(
		array(
			'q' => '',
		),
		(array) $atts,
		'faq_item'
	);

	$question = trim( wp_strip_all_tags( (string) $atts['q'] ) );
	$answer   = trim( (string) $content );

	if ( '' === $question || '' === $answer ) {
		return '';
	}

	$buffer   = &astra_child_seo_faq_buffer();
	$buffer[] = array(
		'q' => $question,
		'a' => wp_strip_all_tags( do_shortcode( $answer ) ),
	);

	$index = count( $buffer );

	return sprintf(
		'<details class="ac-faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">'
			. '<summary itemprop="name">%1$s</summary>'
			. '<div class="ac-faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">'
				. '<div itemprop="text">%2$s</div>'
			. '</div>'
		. '</details>',
		esc_html( $question ),
		wp_kses_post( do_shortcode( $answer ) )
	) . ( $index ? '' : '' ); // index kept for future numbering hooks.
}

/**
 * Outer [faq] shortcode. Resets the buffer, renders inner items, then
 * appends a single FAQPage JSON-LD <script> covering everything captured.
 *
 * @param array<string,string>|string $atts    Shortcode attributes (unused).
 * @param string|null                 $content Inner content with [faq_item]s.
 * @return string
 */
function astra_child_seo_shortcode_faq( $atts, $content = null ) {
	unset( $atts );

	$buffer    = &astra_child_seo_faq_buffer();
	$buffer    = array();
	$rendered  = do_shortcode( (string) $content );
	$collected = $buffer;
	$buffer    = array();

	if ( empty( $collected ) ) {
		return $rendered;
	}

	$entities = array();
	foreach ( $collected as $item ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $item['q'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $item['a'],
			),
		);
	}

	$ld = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);

	$json = wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	return sprintf(
		'<div class="ac-faq" itemscope itemtype="https://schema.org/FAQPage">%1$s</div>'
		. '<script type="application/ld+json">%2$s</script>',
		$rendered,
		$json
	);
}

add_shortcode( 'faq', 'astra_child_seo_shortcode_faq' );
add_shortcode( 'faq_item', 'astra_child_seo_shortcode_faq_item' );

/**
 * Minimal styling for the FAQ accordion.
 *
 * Inlined (small footprint, no extra HTTP request) and only when the
 * shortcode is registered.
 */
function astra_child_seo_faq_inline_style() {
	if ( is_admin() ) {
		return;
	}

	$css = '.ac-faq{margin:1.5em 0}.ac-faq-item{border:1px solid #e5e5e5;border-radius:6px;margin-bottom:.5em;padding:.75em 1em}.ac-faq-item[open]{background:#fafafa}.ac-faq-item summary{cursor:pointer;font-weight:600;list-style:none}.ac-faq-item summary::-webkit-details-marker{display:none}.ac-faq-item summary::after{content:"+";float:left;margin-inline-start:.5em}.ac-faq-item[open] summary::after{content:"-"}.ac-faq-answer{padding-top:.75em;line-height:1.7}';

	printf( '<style id="ac-faq-inline">%s</style>', $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'astra_child_seo_faq_inline_style', 99 );
