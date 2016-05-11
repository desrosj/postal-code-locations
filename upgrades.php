<?php

/**
 * Class PCL_Upgrades
 */
class PCL_Upgrades {

	/**
	 * PCL_Upgrades constructor.
	 */
	function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	/**
	 * Check for and perform any upgrades.
	 */
	function admin_init() {
		if ( 0 > version_compare( PCL_DATABASE_VERSION, '0.1' ) ) {
			$this->version_0_1();
		}

		if ( 0 > version_compare( PCL_DATABASE_VERSION, '0.2' ) ) {
			$this->version_0_2();
		}
	}

	/**
	 * Initial upgrade routine. Add country terms.
	 */
	function version_0_1() {
		$countries = pcl_get_countries();

		foreach ( $countries as $country_code => $country ) {
			if ( ! term_exists( $country, 'pcl_country' ) ) {
				wp_insert_term( $country, 'pcl_country' );
			}
		}

		update_option( 'pcl_version', '0.1' );
	}

	/**
	 * Add country codes to term meta for countries.
	 */
	function version_0_2() {
		$countries = pcl_get_countries();

		foreach ( $countries as $country_code => $country ) {
			$country_term = get_term_by( 'name', $country, 'pcl_country' );

			if ( ! empty( $country_term ) && ! is_wp_error( $country_term ) ) {
				update_term_meta( $country_term->term_id, '_pcl_country_code', $country_code );
			}
		}

		update_option( 'pcl_version', '0.2' );
	}
}
$pcl_upgrades = new PCL_Upgrades();