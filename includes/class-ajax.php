<?php

class GravityView_Ajax {

	function __construct() {

		//get field options
		add_action( 'wp_ajax_gv_field_options', array( $this, 'get_field_options' ) );

		// get available fields
		add_action( 'wp_ajax_gv_available_fields', array( $this, 'get_available_fields_html' ) );

		// get active areas
		add_action( 'wp_ajax_gv_get_active_areas', array( $this, 'get_active_areas' ) );

		// get preset fields
		add_action( 'wp_ajax_gv_get_preset_fields', array( $this, 'get_preset_fields_config' ) );

		// get preset fields
		add_action( 'wp_ajax_gv_set_preset_form', array( $this, 'create_preset_form' ) );

		add_action( 'wp_ajax_gv_sortable_fields_form', array( $this, 'get_sortable_fields' ) );

		// new react stuff @todo: deprecated!!
		add_action( 'wp_ajax_gv_get_form_links', array( $this, 'get_form_links' ) );

		// Load Layout configuration (new admin)
		add_action( 'wp_ajax_gv_get_saved_layout', array( $this, 'get_saved_layout' ) );

		// Load the fields list
		add_action( 'wp_ajax_gv_get_fields_list', array( $this, 'get_available_fields_list' ) );

		// Load the field settings
		add_action( 'wp_ajax_gv_get_field_settings_values', array( $this, 'get_field_settings_values' ) );

		// Load the field settings
		add_action( 'wp_ajax_gv_get_field_settings', array( $this, 'get_field_settings' ) );
	}

	/**
	 * Handle exiting the script (for unit testing)
	 *
	 * @since 1.15
	 * @param bool|false $mixed
	 *
	 * @return bool
	 */
	private function _exit( $mixed = NULL ) {

		/**
		 * Don't exit if we're running test suite.
		 * @since 1.15
		 */
		if( defined( 'DOING_GRAVITYVIEW_TESTS' ) && DOING_GRAVITYVIEW_TESTS ) {
			return $mixed;
		}

		exit( $mixed );
	}

	/** -------- AJAX ---------- */

	/**
	 * Verify the nonce. Exit if not verified.
	 * @return void
	 */
	function check_ajax_nonce() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'gravityview_ajaxviews' ) ) {
			$this->_exit( false );
		}
	}

	/**
	 * Returns available fields given a form ID or a preset template ID
	 * AJAX callback
	 *
	 * @access public
	 * @return void
	 */
	function get_available_fields_html() {

		//check nonce
		$this->check_ajax_nonce();

		$context = isset($_POST['context']) ? esc_attr( $_POST['context'] ) : 'directory';

		// If Form was changed, JS sends form ID, if start fresh, JS sends template_id
		if( !empty( $_POST['form_id'] ) ) {
			do_action( 'gravityview_render_available_fields', (int) $_POST['form_id'], $context );
			$this->_exit();
		} elseif( !empty( $_POST['template_id'] ) ) {
			$form = GravityView_Ajax::pre_get_form_fields( $_POST['template_id'] );
			do_action( 'gravityview_render_available_fields', $form, $context );
			$this->_exit();
		}

		//if everything fails..
		$this->_exit( false );
	}


	/**
	 * Returns template active areas given a template ID
	 * AJAX callback
	 *
	 * @access public
	 * @return void
	 */
	function get_active_areas() {
		$this->check_ajax_nonce();

		if( empty( $_POST['template_id'] ) ) {
			$this->_exit( false );
		}

		ob_start();
		do_action( 'gravityview_render_directory_active_areas', $_POST['template_id'], 'directory', '', true );
		$response['directory'] = ob_get_clean();

		ob_start();
		do_action( 'gravityview_render_directory_active_areas',  $_POST['template_id'], 'single', '', true );
		$response['single'] = ob_get_clean();

		$response = array_map( 'gravityview_strip_whitespace', $response );

		$this->_exit( json_encode( $response ) );
	}

	/**
	 * Fill in active areas with preset configuration according to the template selected
	 * @return void
	 */
	function get_preset_fields_config() {

		$this->check_ajax_nonce();

		if( empty( $_POST['template_id'] ) ) {
			$this->_exit( false );
		}

		// get the fields xml config file for this specific preset
		$preset_fields_path = apply_filters( 'gravityview_template_fieldsxml', array(), $_POST['template_id'] );
		// import fields
		if( !empty( $preset_fields_path ) ) {
			$presets = $this->import_fields( $preset_fields_path );
		} else {
			$presets = array( 'widgets' => array(), 'fields' => array() );
		}

		$template_id = esc_attr( $_POST['template_id'] );

		// template areas
		$template_areas_directory = apply_filters( 'gravityview_template_active_areas', array(), $template_id, 'directory' );
        $template_areas_single = apply_filters( 'gravityview_template_active_areas', array(), $template_id, 'single' );

		// widget areas
		$default_widget_areas = GravityView_Plugin::get_default_widget_areas();

		ob_start();
		do_action('gravityview_render_active_areas', $template_id, 'widget', 'header', $default_widget_areas, $presets['widgets'] );
		$response['header'] = ob_get_clean();

		ob_start();
		do_action('gravityview_render_active_areas', $template_id, 'widget', 'footer', $default_widget_areas, $presets['widgets'] );
		$response['footer'] = ob_get_clean();

		ob_start();
		do_action('gravityview_render_active_areas', $template_id, 'field', 'directory', $template_areas_directory, $presets['fields'] );
		$response['directory'] = ob_get_clean();

		ob_start();
		do_action('gravityview_render_active_areas', $template_id, 'field', 'single', $template_areas_single, $presets['fields'] );
		$response['single'] = ob_get_clean();

		$response = array_map( 'gravityview_strip_whitespace', $response );

		do_action( 'gravityview_log_debug', '[get_preset_fields_config] AJAX Response', $response );

		$this->_exit( json_encode( $response ) );
	}

	/**
	 * Create the preset form requested before the View save
	 *
	 * @return void
	 */
	function create_preset_form() {

		$this->check_ajax_nonce();

		if( empty( $_POST['template_id'] ) ) {
			do_action( 'gravityview_log_error', '[create_preset_form] Cannot create preset form; the template_id is empty.' );
			$this->_exit( false );
		}

		// get the xml for this specific template_id
		$preset_form_xml_path = apply_filters( 'gravityview_template_formxml', '', $_POST['template_id'] );

		// import form
		$form = $this->import_form( $preset_form_xml_path );

		// get the form ID
		if( false === $form ) {
			// send error to user
			do_action( 'gravityview_log_error', '[create_preset_form] Error importing form for template id: ' . (int) $_POST['template_id'] );

			$this->_exit( false );
		}

		$this->_exit( '<option value="'.esc_attr( $form['id'] ).'" selected="selected">'.esc_html( $form['title'] ).'</option>' );

	}

	/**
	 * Import Gravity Form XML or JSON
	 *
	 * @param  string $xml_or_json_path Path to form XML or JSON file
	 * @return int|bool       Imported form ID or false
	 */
	function import_form( $xml_or_json_path = '' ) {

		do_action( 'gravityview_log_debug', '[import_form] Import Preset Form. (File)', $xml_or_json_path );

		if( empty( $xml_or_json_path ) || !class_exists('GFExport') || !file_exists( $xml_or_json_path ) ) {
			do_action( 'gravityview_log_error', '[import_form] Class GFExport or file not found. file: ', $xml_or_json_path );
			return false;
		}

		// import form
		$forms = '';
		$count = GFExport::import_file( $xml_or_json_path, $forms );

		do_action( 'gravityview_log_debug', '[import_form] Importing form (Result)', $count );
		do_action( 'gravityview_log_debug', '[import_form] Importing form (Form) ', $forms );

		if( $count != 1 || empty( $forms[0]['id'] ) ) {
			do_action( 'gravityview_log_error', '[import_form] Form Import Failed!' );
			return false;
		}

		// import success - return form id
		return $forms[0];
	}


	/**
	 * Returns field options - called by ajax when dropping fields into active areas
	 * AJAX callback
	 *
	 * @access public
	 * @return void
	 */
	function get_field_options() {
		$this->check_ajax_nonce();

		if( empty( $_POST['template'] ) || empty( $_POST['area'] ) || empty( $_POST['field_id'] ) || empty( $_POST['field_type'] ) ) {
			do_action( 'gravityview_log_error', '[get_field_options] Required fields were not set in the $_POST request. ' );
			$this->_exit( false );
		}

		// Fix apostrophes added by JSON response
		$_post = array_map( 'stripslashes_deep', $_POST );

		// Sanitize
		$_post = array_map( 'esc_attr', $_post );

		// The GF type of field: `product`, `name`, `creditcard`, `id`, `text`
		$input_type = isset($_post['input_type']) ? esc_attr( $_post['input_type'] ) : NULL;
		$context = isset($_post['context']) ? esc_attr( $_post['context'] ) : NULL;

		$response = GravityView_Render_Settings::render_field_options( $_post['field_type'], $_post['template'], $_post['field_id'], $_post['field_label'], $_post['area'], $input_type, '', '', $context  );

		$response = gravityview_strip_whitespace( $response );

		$this->_exit( $response );
	}

	/**
	 * Given a View id, calculates the assigned form, and returns the form fields (only the sortable ones )
	 * AJAX callback
	 *
	 *
	 * @access public
	 * @return void
	 */
	function get_sortable_fields() {
		$this->check_ajax_nonce();

		$form = '';

		// if form id is set, use it, else, get form from preset
		if( !empty( $_POST['form_id'] ) ) {

			$form = (int) $_POST['form_id'];

		}
		// get form from preset
		elseif( !empty( $_POST['template_id'] ) ) {

			$form = GravityView_Ajax::pre_get_form_fields( $_POST['template_id'] );

		}

		$response = gravityview_get_sortable_fields( $form );

		$response = gravityview_strip_whitespace( $response );

		$this->_exit( $response );
	}

	/**
	 * Get the the form fields for a preset (no form created yet)
	 * @param  string $template_id Preset template
	 *
	 */
	static function pre_get_form_fields( $template_id = '') {

		if( empty( $template_id ) ) {
			do_action( 'gravityview_log_error', __METHOD__ . ' - Template ID not set.' );
			return false;
		} else {
			$form_file = apply_filters( 'gravityview_template_formxml', '', $template_id );
			if( !file_exists( $form_file )  ) {
				do_action( 'gravityview_log_error', __METHOD__ . ' - Importing Form Fields for preset ['. $template_id .']. File not found. file: ' . $form_file );
				return false;
			}
		}

		// Load xml parser (from GravityForms)
		if( class_exists( 'GFCommon' ) ) {
			$xml_parser = GFCommon::get_base_path() . '/xml.php';
		} else {
			$xml_parser = trailingslashit( WP_PLUGIN_DIR ) . 'gravityforms/xml.php';
		}

		if( file_exists( $xml_parser ) ) {
			require_once( $xml_parser );
		} else {
			do_action( 'gravityview_log_debug', __METHOD__ . ' - Gravity Forms XML Parser not found.', $xml_parser );
			return false;
		}

		// load file
		$xmlstr = file_get_contents( $form_file );

        $options = array(
            "page" => array("unserialize_as_array" => true),
            "form"=> array("unserialize_as_array" => true),
            "field"=> array("unserialize_as_array" => true),
            "rule"=> array("unserialize_as_array" => true),
            "choice"=> array("unserialize_as_array" => true),
            "input"=> array("unserialize_as_array" => true),
            "routing_item"=> array("unserialize_as_array" => true),
            "creditCard"=> array("unserialize_as_array" => true),
            "routin"=> array("unserialize_as_array" => true),
            "confirmation" => array("unserialize_as_array" => true),
            "notification" => array("unserialize_as_array" => true)
        );

		$xml = new RGXML($options);
        $forms = $xml->unserialize($xmlstr);

        if( !$forms ) {
        	do_action( 'gravityview_log_error', '[pre_get_available_fields] Importing Form Fields for preset ['. $template_id .']. Error importing file. (File)', $form_file );
        	return false;
        }

        if( !empty( $forms[0] ) && is_array( $forms[0] ) ) {
        	$form = $forms[0];
        }

        if( empty( $form ) ) {
        	do_action( 'gravityview_log_error', '[pre_get_available_fields] $form not set.', $forms );
        	return false;
        }

        do_action( 'gravityview_log_debug', '[pre_get_available_fields] Importing Form Fields for preset ['. $template_id .']. (Form)', $form );

        return $form;

	}


	/**
	 * Import fields configuration from an exported WordPress View preset
	 * @param  string $file path to file
	 * @return array       Fields config array (unserialized)
	 */
	function import_fields( $file ) {

		if( empty( $file ) || !file_exists(  $file ) ) {
			do_action( 'gravityview_log_error', '[import_fields] Importing Preset Fields. File not found. (File)', $file );
			return false;
		}

		if( !class_exists('WXR_Parser') ) {
			include_once GRAVITYVIEW_DIR . 'includes/lib/xml-parsers/parsers.php';
		}

		$parser = new WXR_Parser();
		$presets = $parser->parse( $file );

		if(is_wp_error( $presets )) {
			do_action( 'gravityview_log_error', '[import_fields] Importing Preset Fields failed. Threw WP_Error.', $presets );
			return false;
		}

		if( empty( $presets['posts'][0]['postmeta'] ) && !is_array( $presets['posts'][0]['postmeta'] ) ) {
			do_action( 'gravityview_log_error', '[import_fields] Importing Preset Fields failed. Meta not found in file.', $file );
			return false;
		}

		do_action( 'gravityview_log_debug', '[import_fields] postmeta', $presets['posts'][0]['postmeta'] );

		$fields = $widgets = array();
		foreach( $presets['posts'][0]['postmeta'] as $meta ) {
			switch ($meta['key']) {
				case '_gravityview_directory_fields':
					$fields = maybe_unserialize( $meta['value'] );
					break;
				case '_gravityview_directory_widgets':
					$widgets = maybe_unserialize( $meta['value'] );
					break;
			}
		}

		do_action( 'gravityview_log_debug', '[import_fields] Imported Preset (Fields)', $fields );
		do_action( 'gravityview_log_debug', '[import_fields] Imported Preset (Widgets)', $widgets );

		return array(
			'fields' => $fields,
			'widgets' => $widgets
		);
	}


    /**
     * Get the links relating to a view connected form, like Edit, Entries, Settings, Preview
     * AJAX callback
     */
	function get_form_links() {

		//check nonce
		$this->check_ajax_nonce();

		// check param
		if( empty( $_POST['form'] ) || empty( $_POST['view'] ) ) {
			wp_send_json_error();
		}

		// get links
		$links = GravityView_Admin_Views::get_connected_form_links( $_POST['form'], $_POST['view'] );

		// success
		wp_send_json_success( $links );
	}

	/**
	 * Load the saved View Layout (new structure)
	 */
	function get_saved_layout() {
		//check nonce
		$this->check_ajax_nonce();

		// check param
		if( empty( $_POST['view'] ) ) {
			wp_send_json_error();
		}

		$layout = $this->convert_layout( $_POST['view'] );

		// success
		wp_send_json_success( $layout );
	}

	/**
	 * Convert the old structure of View configuration data into new layout structure (react)
	 * todo: move these functions to a migration class
	 * @param $view_id
	 * @return array
	 */
	function convert_layout( $view_id ) {

		$template_id = get_post_meta( $view_id, '_gravityview_directory_template', true );

		$layout = array(
				'directory' => '',
				'single' 	=> '',
				'edit' 		=> '',
				'export' 	=> ''
		);

		// template
		foreach ( $layout as $k => $v ) {
			$layout[ $k ]['type'] = $template_id;
		}

		// fields
		$this->layout_convert_fields( $layout, $view_id );

		// widgets
		$this->layout_convert_widgets( $layout, $view_id );

		return $layout;

	}

	/**
	 * Convert old view fields data structure into new admin layout structure (react)
	 * todo: move these functions to a migration class
	 * @param $layout array Complete View layout
	 * @param $view_id int View ID
	 */
	function layout_convert_fields( &$layout, $view_id ) {

		$old_fields = get_post_meta( $view_id, '_gravityview_directory_fields', true );
		$form_id = get_post_meta( $view_id, '_gravityview_form_id', true  );
		$form = GVCommon::get_form( $form_id );

		if( empty( $old_fields ) ) {
			return;
		}

		$tab = '';

		foreach( $old_fields as $area => $fields ) {

			$indexs = explode( '_',  $area );
			$i =  $indexs[0] != $tab ? 0 : $i;
			$tab = $indexs[0]; // directory, single, edit, export
			$col = $indexs[1]; // e.g.: list-title, table-columns..

			$layout[ $tab ]['rows'][ $i ]['atts'] = array( 'id' => '', 'class' => '', 'style' => '' );
			$layout[ $tab ]['rows'][ $i ]['id'] = uniqid( '', false );

			switch ( $col ) {
				case 'list-image':
				case 'list-description':
					$col_index = $col === 'list-image' ? 0 : 1;
					$layout[ $tab ]['rows'][ $i ]['columns'][0]['colspan'] = 4;
					$layout[ $tab ]['rows'][ $i ]['columns'][1]['colspan'] = 8;
					break;

				case 'list-footer-left':
				case 'list-footer-right':
					$col_index = $col === 'list-footer-left' ? 0 : 1;
					$layout[ $tab ]['rows'][ $i ]['columns'][0]['colspan'] = 6;
					$layout[ $tab ]['rows'][ $i ]['columns'][1]['colspan'] = 6;
					break;

				case 'list-title':
				case 'list-subtitle':
				case 'table-columns':
				case 'edit-fields':
				default:
					$col_index = 0;
					$layout[ $tab ]['rows'][ $i ]['columns'][ $col_index ]['colspan'] = 12;
					break;

			} // switch

			$layout[ $tab ]['rows'][ $i ]['columns'][ $col_index ]['atts'] = array( 'id' => '', 'class' => '', 'style' => '' );
			$layout[ $tab ]['rows'][ $i ]['columns'][ $col_index ]['fields'] = $this->get_converted_fields( $fields, $form );

			if( ! in_array( $col, array( 'list-image', 'list-footer-left' ) ) ) {
				$i++;
			}

		}

	}

	/**
	 * Helper function to convert a list of fields (old format) to a new structure (react)
	 * todo: move these functions to a migration class
	 * @param $fields array List of fields details
	 * @param $form array Gravity Forms Form object
	 * @return array
	 */
	function get_converted_fields( $fields, $form ) {

		$output = array();

		if( empty( $fields ) ) {
			return $output;
		}
		$i = 0;
		foreach( $fields as $k => $field ) {
			$output[ $i ]['id'] = $k;
			$output[ $i ]['form_id'] = $form['id'];
			$output[ $i ]['field_id'] = $field['id'];
			$output[ $i ]['field_type'] = GVCommon::get_field_type( $form, $field['id'] );
			unset( $field['id'] );
			$output[ $i ]['gv_settings'] = $field;
			$i++;
		}

		return $output;
	}

	/**
	 * Convert old view widgets data structure into new admin layout structure (react)
	 * todo: move these functions to a migration class
	 * @param $layout array Complete View layout
	 * @param $view_id int View ID
	 */
	function layout_convert_widgets( &$layout, $view_id ) {
		$old_widgets = get_post_meta( $view_id, '_gravityview_directory_widgets', true );

		if( empty( $old_widgets ) ) {
			return;
		}

		$place = '';

		foreach ( $old_widgets as $area => $widgets ) {
			$indexs = explode( '_',  $area );

			$i =  $indexs[0] != $place ? 0 : $i; // Rows array index

			$place = $indexs[0] === 'header' ? 'above' : 'below';

			$layout['directory']['widgets'][ $place ]['rows'][ $i ]['atts'] = array( 'id' => '', 'class' => '', 'style' => '' );
			$layout['directory']['widgets'][ $place ]['rows'][ $i ]['id'] = uniqid( '', false );

			if( $indexs[1] === 'top' ) {
				$layout['directory']['widgets'][ $place ]['rows'][ $i ]['columns'][0]['colspan'] = 12;
			} else {
				$layout['directory']['widgets'][ $place ]['rows'][ $i ]['columns'][0]['colspan'] = 6;
				$layout['directory']['widgets'][ $place ]['rows'][ $i ]['columns'][1]['colspan'] = 6;
			}
			$col_index = $indexs[1] === 'right' ? 1 : 0;

			$layout['directory']['widgets'][ $place ]['rows'][ $i ]['columns'][ $col_index ]['atts'] = array( 'id' => '', 'class' => '', 'style' => '' );
			$layout['directory']['widgets'][ $place ]['rows'][ $i ]['columns'][ $col_index ]['fields'] = $this->get_converted_widgets( $widgets );

			if( $indexs[1] !== 'left' ) {
				$i++; // only change row if column is top or right
			}

		}


	}

	/**
	 * Helper function to convert a list of widgets (old format) to a new structure (react)
	 * todo: move these functions to a migration class
	 * @param $widgets array List of widgets details
	 * @return array
	 */
	function get_converted_widgets( $widgets ) {

		$output = array();

		if( empty( $widgets ) ) {
			return $output;
		}
		$i = 0;
		foreach( $widgets as $k => $widget ) {
			$output[ $i ]['id'] = $k;
			$output[ $i ]['widget'] = $widget['id'];
			unset( $widget['id'] );
			$output[ $i ]['gv_settings'] = $widget;
			$i++;
		}

		return $output;
	}


	/**
	 * Get available fields for a form and a context (directory, single, edit..)
	 * @param string $form
	 * @param string $context
	 */
	function get_available_fields_list() {

		//check nonce
		$this->check_ajax_nonce();

		// check param
		if( empty( $_POST['forms'] ) ) {
			wp_send_json_error();
		}

		$forms =  $_POST['forms'];

		$output = '';

		/**
		 * @filter  `gravityview_blacklist_field_types` Modify the types of fields that shouldn't be shown in a View.
		 * @param[in,out] array $blacklist_field_types Array of field types to block for this context.
		 * @param[in] string $context View context ('single', 'directory', or 'edit')
		 */
		foreach( array( 'directory', 'single', 'edit', 'export' ) as $context  ) {


			$blacklist_field_types = apply_filters( 'gravityview_blacklist_field_types', array(), $context );

			$fields = GravityView_Admin_Views::get_instance()->get_available_fields( $forms[0], $context );

			/**
			 * Loop to create an object of fields grouped by section (form, entry, gravityview...)
			 */
			if( !empty( $fields ) ) {

				foreach( $fields as $id => $details ) {

					if( in_array( $details['type'], $blacklist_field_types ) ) {
						continue;
					}

					// Edit mode only allows editing the parent fields, not single inputs.
					if( $context === 'edit' && !empty( $details['parent'] ) ) {
						continue;
					}

					if( empty( $details['group'] ) ) {
						$details['group'] = 'form';
						$details['form_id'] = $forms[0];
					}

					$details['id'] = (string)$id;

					$output[ $context ][ $details['group'] ][] = $details;

				} // End foreach
			}
		}

		// success
		wp_send_json_success( $output );

		//todo: do we want to have the ADD ALL FIELDS field ?
		//$this->render_additional_fields( $form, $context );
	}


	/**
	 * Returns field options values - called by ajax when dropping fields into active areas
	 * AJAX callback
	 *
	 * @access public
	 * @return void
	 */
	function get_field_settings_values() {
		$this->check_ajax_nonce();



		if( empty( $_POST['field_id'] ) || empty( $_POST['field_type'] ) || empty( $_POST['field_label'] ) ) {
			do_action( 'gravityview_log_error', '[get_field_settings] Required fields were not set in the $_POST request. ' );
			$this->_exit( false );
		}

		// Fix apostrophes added by JSON response
		$_post = array_map( 'stripslashes_deep', $_POST );

		// Sanitize
		$_post = array_map( 'esc_attr', $_post );

		// The GF type of field: `product`, `name`, `creditcard`, `id`, `text`
		$input_type = isset( $_post['input_type'] ) ? esc_attr( $_post['input_type'] ) : NULL;
		$context = isset( $_post['context'] ) ? esc_attr( $_post['context'] ) : NULL;

		$options = GravityView_Render_Settings::get_default_field_options( 'field' , '', $_POST['field_id'] , $context , $input_type );

		// add the received field label
		$output['label'] = $_POST['field_label'];

		foreach ( $options as $id => $option ) {
			$output[ $id ] = isset( $option['value'] ) ? $option['value'] : null;
		}

		wp_send_json_success( $output );
	}

	/**
	 * Returns field options - called by ajax when dropping fields into active areas
	 * AJAX callback
	 *
	 * @access public
	 * @return void
	 */
	function get_field_settings() {
		$this->check_ajax_nonce();


		$output = '';
		if( empty( $_POST['field_id'] ) || empty( $_POST['field_type'] ) ) {
			do_action( 'gravityview_log_error', '[get_field_settings] Required fields were not set in the $_POST request. ' );
			$this->_exit( false );
		}

		// Fix apostrophes added by JSON response
		$_post = array_map( 'stripslashes_deep', $_POST );

		// Sanitize
		$_post = array_map( 'esc_attr', $_post );

		// The GF type of field: `product`, `name`, `creditcard`, `id`, `text`
		$input_type = isset( $_post['input_type'] ) ? esc_attr( $_post['input_type'] ) : NULL;
		$context = isset( $_post['context'] ) ? esc_attr( $_post['context'] ) : NULL;

		$options = GravityView_Render_Settings::get_default_field_options( 'field' , '', $_POST['field_id'] , $context , $input_type );

		foreach ( $options as $id => $option ) {

			$option['id'] = $id;

			if( !empty( $option['options'] ) ) {
				$new_options = array();
				foreach( $option['options'] as $k => $v ) {
					$new_options[] = array( 'value' => $k, 'label' => $v );
				}
				$option['options'] = $new_options;
				unset( $new_options );
			}

			$output[] = $option;

		}

		wp_send_json_success( $output );
	}


}

new GravityView_Ajax;
