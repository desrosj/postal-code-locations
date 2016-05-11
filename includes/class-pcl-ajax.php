<?php

/**
 * Class PCL_AJAX
 */
class PCL_AJAX {

	/**
	 * Add our hooks.
	 */
	function __contruct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );
	}

	/**
	 * Register endpoints.
	 */
	function init() {
		add_rewrite_tag( '%country_term_id%', '([0-9]+)' );
		add_rewrite_rule( '^country-check/([^/]*)/?', 'index.php?country_term_id=$matches[1]', 'top' );
	}

	/**
	 * Handle AJAX requests for checking country support for postal codes.
	 */
	function template_redirect() {
		global $wp_query;

		$country = strtoupper( sanitize_text_field( $wp_query->get( 'country_abbreviation' ) ) );

		if ( empty( $country ) ) {
			return;
		}

		$result = array(
			'result' => false,
			'errors' => new WP_Error(),
		);

		if ( empty( $country ) || 2 < strlen( $country ) ) {
			$result['errors']->add( 'pcl_country_support_error', __('There was an error selecting that country.', 'pcl') );
		}

		if ( function_exists( 'wpcom_vip_get_term_by' ) ) {
			$country_term = wpcom_vip_get_term_by( 'name', $country, 'pcl_postal_code' );
		} else {
			$country_term = get_term_by( 'name', $country, 'pcl_postal_code' );
		}

		if ( is_wp_error( $country_term ) ) {
			$result['errors']->add( 'pcl_country_support_error', __('There was an error selecting that country.', 'pcl') );
		}

		//Return the location term information if there is no country support
		if ( empty( $country_term ) ) {
			$countries = pcl_get_countries();

			//Try to return the country taxonomy term instead to link to/filter by.
			if ( isset( $countries[ $country ] ) ) {
				$location_term = get_term_by( 'name', $countries[ $country ], 'pcl_country' );

				if ( empty( $location_term ) || is_wp_error( $location_term ) ) {
					$result['errors']->add( 'pcl_country_support_error', __('There was an error selecting that country.', 'pcl') );
				} else {
					if ( empty( $location_term->count ) ) {
						$result['errors']->add( 'pcl_country_empty', __('There were no profiles found in the selected country.', 'pcl') );
					} else {
						$result['country_term'] = array(
							'term_id' => $location_term->term_id,
							'name'    => $location_term->name,
							'slug'    => $location_term->slug,
							'count'   => $location_term->count,
						);

						$link = get_term_link( $location_term );

						if ( empty( $link ) || is_wp_error( $link ) ) {
							$result['errors']->add( 'pcl_country_support_error', __('There was an error selecting that country.', 'pcl') );
						}

						$result['country_term']['link'] = $link;
					}
				}
			}
		}

		//No errors and no country term fallback means support is there
		if ( empty( $result['country_term'] ) && empty( $result['errors']->get_error_codes() ) ) {
			$result['result'] = true;
		}

		wp_send_json( $result );
	}

	/**
	 * @param $template
	 *
	 * @return string
	 */
	function template_include( $template ) {
		global $wp_query;

		if ( ! get_query_var( 'pcl_is_location_search' ) ) {
			return $template;
		}

		$new_template = locate_template( array( 'archive.php' ) );

		if ( empty( $new_template ) ) {
			return $template;
		}

		return $new_template;
	}
}
$pcl_ajax = new PCL_AJAX();