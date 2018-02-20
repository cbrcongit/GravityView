<?php
/**
 * The default select field output template.
 *
 * @global \GV\Template_Context $gravityview
 * @since future
 */
$field = $gravityview->field->field;
$entry = $gravityview->entry->as_entry();
$field_settings = $gravityview->field->as_configuration();

/**
 * @filter `gravityview/fields/select/output_label` Override whether to show the value or the label of a Select field
 * @since 1.5.2
 * @param bool $show_label True: Show the label of the Choice; False: show the value of the Choice. Default: `false`
 * @param array $entry GF Entry
 * @param GF_Field_Select $field Gravity Forms Select field
 */
$show_label = apply_filters( 'gravityview/fields/select/output_label', ( 'label' === \GV\Utils::get( $field_settings, 'choice_display' ) ), $entry, $field );

$output = $field->get_value_entry_detail( $gravityview->value, '', $show_label );

echo $output;