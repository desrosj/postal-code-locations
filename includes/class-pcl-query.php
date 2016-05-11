<?php

class PCL_Query {

	function __construct() {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
	}

	/**
	 * Add necessary query vars for passing radius search information.
	 * 
	 * @param $vars
	 *
	 * @return array
	 */
	function query_vars( $vars ) {
		return array_merge( $vars, array(
			'pcl_radius',
			'pcl_postal_code',
			'pcl_country_code',
			'pcl_longitude',
			'pcl_latitude',
			'pcl_distance_unit',
			'pcl_is_location_search',
		) );
	}

	/**
	 * Handle radius searching.
	 *
	 * Use a cached list of post IDs from the defined parameters, and generate a list/cache if one does not exist.
	 *
	 * @param $wp_query
	 */
	function pre_get_posts( $wp_query ) {
		if ( 'prowp_profile' != $wp_query->get( 'post_type' ) ) {
			return;
		}

		if ( empty( $wp_query->get( 'pcl_radius' ) ) ) {
			$wp_query->set( 'pcl_is_location_search', 0 );
			return;
		}

		$distance = intval( $wp_query->get( 'pcl_radius' ) );

		$distance_unit = strtolower( $wp_query->get( 'pcl_distance_unit' ) );

		if ( 'mi' == $distance_unit ) {
			$earth_radius = 3959;
		} else {
			$earth_radius = 6371;
			$wp_query->set( 'pcl_distance_unit', 'km' );
		}

		$latitude = $wp_query->get( 'pcl_latitude' );
		$longitude = $wp_query->get( 'pcl_longitude' );
		$postal_code = $wp_query->get( 'pcl_postal_code' );
		$country_code = strtoupper( $wp_query->get( 'pcl_country_code' ) );
		$country = $wp_query->get( 'countries' );

		//User did not use "use my location", attempt to get coordinates by postal code supplied
		if ( ( empty( $latitude ) && empty( $longitude ) ) ) {
			if ( empty( $postal_code ) ) {
				$wp_query->set( 'pcl_is_location_search', 0 );
				return;
			}

			$supported_countries = get_terms( 'pcl_postal_code', array(
				'hide_empty' => false,
				'parent' => 0,
			) );

			if ( empty( $supported_countries ) || is_wp_error( $supported_countries ) ) {
				$wp_query->set( 'pcl_is_location_search', 0 );
				return;
			}

			$supported_country_codes = wp_list_pluck( $supported_countries, 'name' );

			//If pcl_country_code is not set, use the country taxonomy query var to look up the country.
			if ( empty( $country_code ) ) {
				if ( ! empty( $country ) ) {
					$country_term = get_term_by( 'slug', sanitize_text_field( $country ), 'pcl_country' );

					if ( empty( $country_term ) || is_wp_error( $country_term ) ) {
						$wp_query->set( 'pcl_is_location_search', 0 );
						return;
					} else {
						$country_abbreviations = pcl_get_countries();
						$country_code = array_search( $country_term->name, $country_abbreviations );
					}
				} else {
					$wp_query->set( 'pcl_is_location_search', 0 );
					return;
				}
			}

			if ( ! in_array( $country_code, $supported_country_codes ) ) {
				$wp_query->set( 'pcl_is_location_search', 0 );
				return;
			}

			$postal_code_object = pcl_get_postal_code( $postal_code, $country_code );

			if ( is_wp_error( $postal_code_object ) ) {
				return;
			}

			$latitude = $postal_code_object->latitude;
			$wp_query->set( 'pcl_latitude', $latitude );

			$longitude = $postal_code_object->longitude;
			$wp_query->set( 'pcl_longitude', $longitude );

			$wp_query->set( 'pcl_is_location_search', 1 );
		}

		//Bail if we still do not have a center point latitude and longitude
		if ( empty( $latitude ) || empty( $longitude ) ) {
			$wp_query->set( 'pcl_is_location_search', 0 );
			return;
		}

		$cache_key = md5( $latitude . $longitude . $distance . $distance_unit );

		//Check for cached list of profiles.
		$profiles = wp_cache_get( $cache_key, 'pcl_radius_profiles' );

		$latitude_top = number_format( $latitude - rad2deg( $distance / $earth_radius ), 6 );
		$latitude_bottom = number_format( $latitude + rad2deg( $distance / $earth_radius ), 6 );
		$longitude_left = number_format( $longitude - rad2deg( $distance / $earth_radius / cos( deg2rad( $latitude ) ) ), 6 );
		$longitude_right = number_format( $longitude + rad2deg( $distance / $earth_radius / cos( deg2rad( $latitude ) ) ), 6 );

		//Radius query args
		$radius_args = array(
			'post_type' => 'prowp_profile',
			'post_status' => 'publish',
			'posts_per_page' => 100,
			'offset' => 0,
			'meta_query' => array(
				'relation' => 'AND',
				'geo_query' => array(
					array(
						'key' => 'geo_latitude',
						'compare' => 'BETWEEN',
						'type' => 'DECIMAL',
						'value' => array(
							( $latitude_top < $latitude_bottom ) ? $latitude_top : $latitude_bottom,
							( $latitude_top > $latitude_bottom ) ? $latitude_top : $latitude_bottom,
						),
					),
					array(
						'key' => 'geo_longitude',
						'compare' => 'BETWEEN',
						'type' => 'DECIMAL',
						'value' => array(
							( $longitude_left < $longitude_right ) ? $longitude_left : $longitude_right,
							( $longitude_left > $longitude_right ) ? $longitude_left : $longitude_right,
						),
					),
				),
			),
			'orderby' => array(
				'score_meta' => 'DESC',
				'post_title' => 'ASC'
			),
			'no_found_rows' => true,
			'fields' => 'ids',
		);

		if ( false === $profiles ) {
			$profiles = array();

			$radius_query = new WP_Query( $radius_args );

			while ( $radius_query->have_posts() ) {
				foreach ( $radius_query->posts as $p ) {
					$profile_latitude  = get_post_meta( $p, 'geo_latitude', true );
					$profile_longitude = get_post_meta( $p, 'geo_longitude', true );

					$profile_distance = $earth_radius * acos( sin( deg2rad( $latitude ) ) * sin( deg2rad( $profile_latitude ) ) + cos( deg2rad( $latitude ) ) * cos( deg2rad( $profile_latitude ) ) * cos( deg2rad( $longitude - $profile_longitude ) ) );

					if ( $profile_distance <= $distance ) {
						$profiles[] = $p;
					}
				}

				$radius_args['offset'] = $radius_args['offset'] + $radius_args['posts_per_page'];
				$radius_query = new WP_Query( $radius_args );
			}

			wp_cache_set( $cache_key, $profiles, 'pcl_radius_profiles' );
		}

		$profiles = array_map( 'intval', $profiles );

		$wp_query->set( 'pcl_is_location_search', 1 );

		//No profiles matched, make sure that the query reflects that (passing empty profiles array causes it to be ignored)
		if ( empty( $profiles ) ) {
			$wp_query->set( 'meta_query', $radius_args['meta_query'] );
			return;
		}

		$wp_query->set( 'post__in', $profiles );
	}
}
$pcl_query = new PCL_Query();