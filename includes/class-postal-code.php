<?php

/**
 * Class PCL_Postal_Code
 */
class PCL_Postal_Code {

	public $postal_code;

	public $key;

	public $country_code;

	public $term;

	public $country_term;

	public $latitude;

	public $longitude;

	/**
	 * Retrieve a PCL_Postal_Code instance
	 *
	 * @param $postal_code
	 * @param $country_code
	 *
	 * @return bool|PCL_Postal_Code
	 */
	public static function get_instance( $postal_code, $country_term ) {
		if ( ! $country_term instanceof WP_Term ) {
			$country_term = get_term( (int) $country_term, 'pcl_country' );
		}

		if ( empty( $country_term ) || is_wp_error( $country_term ) ) {
			return false;
		}

		$country_code = pcl_get_country_code( $country_term );
		$postal_code = sanitize_text_field( $postal_code );

		//We need both to proceed
		if ( empty( $country_code ) || empty( $postal_code ) ) {
			return false;
		}

		$_postal_code = wp_cache_get( strtoupper( $country_code ) . '-' . strtoupper( $postal_code ), 'pcl_postal_code' );

		if ( ! $_postal_code ) {
			$_postal_code = new stdClass();
			$_postal_code->postal_code = $postal_code;
			$_postal_code->country_code = $country_code;
			$_postal_code->country_term = $country_term;

			//Fix up our postal code to make sure it plays nice with Zippapotamus
			$_postal_code->postal_code = pcl_validate_postal_code( $_postal_code->country_code, $_postal_code->postal_code );

			//Set a key for caching, and term slugs
			$_postal_code->key = str_replace( ' ', '-', strtoupper( $_postal_code->country_code ) . '-' . strtoupper( $_postal_code->postal_code ) );

			//Look for a valid postal code term, and contain the data we require
			$_term = pcl_postal_code_get_term_by( 'slug', $_postal_code->key );

			if ( $_term ) {
				if ( is_wp_error( $_term ) || empty( $_term ) ) {
					$_postal_code->term = false;
				}

				$_term_latitude = get_term_meta( $_term->term_id, '_pcl_latitude', true );
				$_term_longitude = get_term_meta( $_term->term_id, '_pcl_longitude', true );

				if ( empty( $_term_latitude ) || empty( $_term_longitude ) ) {
					wp_delete_term( $_term->term_id, 'pcl_postal_code' );
					$_postal_code->term = false;
				} else {
					$_postal_code->term = $_term;
				}
			} else {
				$_postal_code->term = false;
			}

			//If a term doesn't exist, let's try to get data from the API and create one
			if ( ! $_postal_code->term ) {
				if ( $postal_code_data = self::lookup_postal_code( $_postal_code->postal_code, $_postal_code->country_code ) ) {

					//If no places are listed, a valid response was received, but it does not have the components that we needed.
					if ( empty( $postal_code_data->places ) ) {
						return new WP_Error( 'pcl_postal_code_no_geo_data', __( 'We are having issues finding this postal code. Try a different one, or use your current location.', 'pcl' ) );
					}

					$coordinates = array();

					//If the postal code represents more than one location, average (center) of their latitudes and longitudes
					if ( 1 < count( $postal_code_data->places ) ) {
						$latitude = 0;
						$longitude = 0;

						foreach ( $postal_code_data->places as $place ) {
							$latitude += floatval( $place->latitude );
							$longitude += floatval( $place->longitude );
						}

						$coordinates['latitude'] = round( $latitude / count( $postal_code_data->places ), 5 );
						$coordinates['longitude'] = round( $longitude / count( $postal_code_data->places ), 5 );
					} else {
						$coordinates['latitude'] = round( floatval( $postal_code_data->places[0]->latitude ), 5 );
						$coordinates['longitude'] = round( floatval( $postal_code_data->places[0]->longitude ), 5 );
					}

					if ( empty( $coordinates ) ) {
						return new WP_Error( 'pcl_postal_code_no_geo_data2', __( 'We are having issues finding this postal code. Try a different one, or use your current location.', 'pcl' ) );
					}

					$new_term_args = array(
						'parent' => $_postal_code->country_term->term_id,
						'slug' => $_postal_code->key,
					);

					if ( ! function_exists( 'update_term_meta' ) ) {
						$new_term_args['description'] = wp_json_encode( $coordinates );
					}

					$new_term = wp_insert_term( $_postal_code->postal_code, 'pcl_postal_code', $new_term_args );

					if ( is_wp_error( $new_term ) ) {
						return false;
					}

					if ( function_exists( 'update_term_meta' ) ) {
						update_term_meta( $new_term['term_id'], '_pcl_latitude', $coordinates['latitude'] );
						update_term_meta( $new_term['term_id'], '_pcl_longitude', $coordinates['longitude'] );
					}

					$_postal_code->term = get_term( $new_term['term_id'], 'pcl_postal_code' );
				} else {
					return new WP_Error( 'pcl_postal_code_lookup_error', __( 'There was an error looking up your postal code.', 'pcl' ) );
				}
			}

			if ( function_exists( 'get_term_meta' ) ) {
				$data = array(
					'latitude' => get_term_meta( $_postal_code->term->term_id, '_pcl_latitude', true ),
					'longitude' => get_term_meta( $_postal_code->term->term_id, '_pcl_longitude', true ),
				);
			} else {
				$data = (array) json_decode( $_postal_code->term->description );
			}

			//If data is missing, delete the term to initiate regrabbing of its info
			if ( empty( $data['latitude'] ) || empty( $data['longitude'] ) ) {
				wp_delete_term( $_postal_code->term->term_id, 'pcl_postal_code' );
				return new WP_Error( 'pcl_postal_code_lookup_error', __( 'We are having issues finding this postal code. Try a different one, or use your current location.', 'pcl' ) );
			}

			$_postal_code->latitude = floatval( $data['latitude'] );
			$_postal_code->longitude = floatval( $data['longitude'] );

			wp_cache_add( $_postal_code->key, $_postal_code, 'pcl_postal_codes' );
		}

		return new PCL_Postal_Code( $_postal_code );
	}

	/**
	 * Constructor.
	 *
	 * @param PCL_Postal_Code|object $postal_code Postal Code object.
	 */
	public function __construct( $postal_code ) {
		foreach ( get_object_vars( $postal_code ) as $key => $value ) {
			$this->$key = $value;
		}
	}

	/**
	 * Grab information about a postal code from Zippopotamus
	 *
	 * @access public
	 * @param mixed $postal_code
	 * @param mixed $country
	 * @return void
	 */
	public static function lookup_postal_code( $postal_code, $country ) {
		$source_url = esc_url( 'https://api.zippopotam.us/' . $country . '/' . $postal_code );

		$response = wp_remote_get( $source_url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return false;
		}

		return json_decode( $body );
	}
}