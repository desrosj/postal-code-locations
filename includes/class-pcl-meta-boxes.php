<?php

class PCL_Meta_Boxes {

	/**
	 * PCL_Meta_Boxes constructor.
	 */
	function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
	}

	/**
	 * @param $post_type
	 * @param $post
	 */
	function add_meta_boxes( $post_type, $post ) {
		if ( ! pcl_has_support( $post_type ) ) {
			return;
		}

		add_meta_box( 'pcl-location-info-meta-box', 'Location Information', array( $this, 'pcl_location_info_meta_box' ), $post_type, 'side', 'high' );
	}

	/**
	 * Meta box for location info.
	 *
	 * @param $post
	 */
	function pcl_location_info_meta_box( $post ) {
		$countries = get_terms( array(
			'hide_empty' => false,
			'taxonomy' => 'pcl_country',
		) );

		wp_nonce_field( 'pcl_update_location_info', 'pcl_location_info_nonce' );
		?>
		<p>
			<label for="pcl_country"><strong>Country:</strong></label>
			<select name="pcl_country">
				<option></option>
				<?php foreach ( $countries as $country ) : ?>
					<option value="<?php esc_attr_e( $country->term_id ); ?>" <?php selected( has_term( (int) $country->term_id, 'pcl_country', $post->ID ) ); ?>><?php echo $country->name; ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="pcl_postal_code"><strong>Postal Code:</strong></label><br />
			<input type="text" id="pcl_postal_code" name="pcl_postal_code" value="<?php esc_attr_e( sanitize_text_field( get_post_meta( $post->ID, '_pcl_postal_code', true ) ) ); ?>" />
		</p>
		<?php
	}

	/**
	 * Save location data.
	 *
	 * @param $post_id
	 * @param $post
	 */
	function save_post( $post_id, $post ) {
		if ( ! pcl_has_support( $post->post_type ) ) {
			return;
		}

		//Skip revisions and autosaves
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		//Users should have the ability to edit listings.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['pcl_location_info_nonce'] ) && wp_verify_nonce( $_POST['pcl_location_info_nonce'], 'pcl_update_location_info' ) ) {
			$country_term_id = 0;
			$postal_code = '';

			// Save the country field.
			if ( empty( $_POST['pcl_country'] ) ) {
				$this->clear_geo_location_data( $post_id, true, true );
				delete_post_meta( $post_id, '_pcl_postal_code' );

				return;
			} else {
				$country_term_check = get_term( (int) $_POST['pcl_country'] );

				if ( empty( $country_term_check ) || is_wp_error( $country_term_check ) ) {
					$this->clear_geo_location_data( $post_id, true, true );
					delete_post_meta( $post_id, '_pcl_postal_code' );

					return;
				} else {
					wp_set_object_terms( $post_id, array( (int) $country_term_check->term_id ), 'pcl_country', false );
				}
			}

			if ( empty( $_POST['pcl_postal_code'] ) ) {
				$this->clear_geo_location_data( $post_id, false, true );
				delete_post_meta( $post_id, '_pcl_postal_code' );
			} else {
				$country_code = pcl_get_country_code( $country_term_check );
				$postal_code = pcl_validate_postal_code( $country_code, sanitize_text_field( $_POST['pcl_postal_code'] ), false );

				$postal_code_object = pcl_get_postal_code( $postal_code, $country_term_check->term_id );

				//Not a postal code supported country
				if ( empty( $postal_code_object ) || is_wp_error( $postal_code_object ) ) {
					$this->clear_geo_location_data( $post_id, false, true );
					delete_post_meta( $post_id, '_pcl_postal_code' );
				} else {
					update_post_meta( $post_id, '_pcl_longitude', $postal_code_object->longitude );
					update_post_meta( $post_id, '_pcl_latitude', $postal_code_object->latitude );
					update_post_meta( $post_id, '_pcl_postal_code', $postal_code );

					if ( ! empty( $postal_code_object->term ) ) {
						wp_set_object_terms( $post_id, array( (int) $postal_code_object->term->term_id ), 'pcl_postal_code', false );
					}
				}
			}
		}
	}

	/**
	 * Clear geolocation data from a post.
	 *
	 * @param $post_id
	 * @param bool $country_term
	 * @param bool $postal_code_term
	 */
	function clear_geo_location_data( $post_id, $country_term = false, $postal_code_term = false ) {
		if ( ! $post = get_post( $post_id ) ) {
			return;
		}

		delete_post_meta( $post_id, '_pcl_latitude' );
		delete_post_meta( $post_id, '_pcl_longitude' );

		if ( $country_term ) {
			wp_set_object_terms( $post_id, null, 'pcl_country', false );
		}

		if ( $postal_code_term ) {
			wp_set_object_terms( $post_id, null, 'pcl_postal_code', false );
		}

		if ( $country_term && $postal_code_term ) {
			delete_post_meta( $post_id, '_pcl_postal_code' );
		}
	}
}
$pcl_meta_boxes = new PCL_Meta_Boxes();