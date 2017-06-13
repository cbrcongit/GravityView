<?php
/**
 * Display the textarea field type
 *
 * Use wpautop() to format paragraphs, as expected, instead of line breaks like Gravity Forms displays by default.
 *
 * @package GravityView
 * @subpackage GravityView/templates/fields
 */

$gravityview_view = GravityView_View::getInstance();

extract( $gravityview_view->getCurrentField() );

/**
 * @filter `gravityview/fields/textarea/allowed_kses` Allow the following HTML tags and strip everything else.
 * @since 1.21.5.1
 * @param array $allowed_html The allowed tags. Default: p, a, b, em, u
 */
$allowed_html = add_filter( 'gravityview/fields/textarea/allowed_kses', array(
	'p' => array(),
	'a' => array( 'href' ),
	'b' => array(),
	'em' => array(),
	'u' => array()
) );
$value = wp_kses( $value, $allowed_html );

if( !empty( $field_settings['trim_words'] ) ) {

	/**
	 * @filter `gravityview_excerpt_more` Modify the "Read more" link used when "Maximum Words" setting is enabled and the output is truncated
	 * @since 1.16.1
	 * @param string $excerpt_more Default: ` ...`
	 */
	$excerpt_more = apply_filters( 'gravityview_excerpt_more', ' ' . '&hellip;' );

	$entry_link = GravityView_API::entry_link_html( $entry, $excerpt_more, array(), $field_settings );
	$value = wp_trim_words( $value, $field_settings['trim_words'], $entry_link );
	unset( $entry_link, $excerpt_more );
}

if( !empty( $field_settings['make_clickable'] ) ) {
    $value = make_clickable( $value );
}

if( ! empty( $field_settings['new_window'] ) ) {
	$value = links_add_target( $value );
}

echo wpautop( $value );

