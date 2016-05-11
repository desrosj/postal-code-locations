<?php

class PCL_Settings {

	function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	function admin_init() {
		register_setting( 'writing', 'pcl_post_types', array( $this, 'sanitize_settings' ) );

		// Add a section for the plugin's settings on the writing page
		add_settings_section( 'pcl_post_types_section', 'Postal Code Post Types', array( $this, 'settings_section_text' ), 'writing' );

		// For each post type add a settings field, excluding revisions and nav menu items
		if ( $post_types = get_post_types() ) {
			foreach ( $post_types as $post_type ) {
				if ( ! $pt = get_post_type_object( $post_type ) ) {
					continue;
				}

				if ( in_array( $post_type, array( 'revision', 'nav_menu_item', 'attachment' ) ) || ! $pt->public ) {
					continue;
				}

				add_settings_field( 'pcl_post_types' . $post_type, $pt->labels->name, array(
					$this,
					'postal_code_post_type_field'
				), 'writing', 'pcl_post_types_section', array( 'slug' => $pt->name, 'name' => $pt->labels->name ) );
			}
		}
	}

	/**
	 * settings_section_text function.
	 *
	 * @access public
	 * @return void
	 */
	function settings_section_text() {
		?>
		<p><?php _e( 'Select which post types are classified by postal code.', 'pcl' ); ?></p>
		<?php
	}

	/**
	 * featured_post_types_field function.
	 *
	 * @access public
	 * @param mixed $args
	 * @return void
	 */
	function postal_code_post_type_field( $args ) {
		$settings = pcl_get_enabled_post_types();
		?>
		<input type="checkbox" name="pcl_post_types[]" id="pcl_post_types_<?php esc_attr_e( $args['slug'] ); ?>" value="<?php esc_attr_e( $args['slug'] ); ?>" <?php checked( in_array( $args['slug'], $settings ) ); ?>/>
		<?php
	}

	/**
	 * Sanitize options before saving.
	 *
	 * @access public
	 * @param mixed $input
	 * @return void
	 */
	function sanitize_settings( $input ) {
		$input = wp_parse_args( $_POST['pcl_post_types'], array() );

		$new_input = array();

		foreach ( $input as $pt ) {
			if ( post_type_exists( sanitize_text_field( $pt ) ) ) {
				$new_input[] = sanitize_text_field( $pt );
			}
		}

		return $new_input;
	}
}
$pcl_settings = new PCL_Settings();