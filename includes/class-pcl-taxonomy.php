<?php

/**
 * Class PCL_Taxonomy
 */
class PCL_Taxonomy {

	/**
	 * PCL_Taxonomy constructor.
	 */
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		add_action( 'edited_pcl_postal_code', array( $this, 'edited_pcl_postal_code' ), 10, 2 );
		add_action( 'delete_pcl_postal_code', array( $this, 'delete_pcl_postal_code' ), 10, 3 );
	}

	/**
	 * Register Countries Taxonomy
	 */
	function init() {
		register_taxonomy( 'pcl_country', pcl_get_enabled_post_types(), array(
			'labels'             => array(
				'name'                       => _x( 'Countries', 'taxonomy general name', 'pcl' ),
				'singular_name'              => _x( 'Country', 'taxonomy singular name', 'pcl' ),
				'search_items'               => __( 'Search Countries', 'pcl' ),
				'popular_items'              => __( 'Popular Countries', 'pcl' ),
				'all_items'                  => __( 'All Countries', 'pcl' ),
				'edit_item'                  => __( 'Edit Country', 'pcl' ),
				'view_item'                  => __( 'View Country', 'pcl' ),
				'update_item'                => __( 'Update Country', 'pcl' ),
				'add_new_item'               => __( 'Add New Country', 'pcl' ),
				'new_item_name'              => __( 'New Country Name', 'pcl' ),
				'separate_items_with_commas' => __( 'Separate countries with commas', 'pcl' ),
				'add_or_remove_items'        => __( 'Add or remove countries', 'pcl' ),
				'choose_from_most_used'      => __( 'Choose from the most used countries', 'pcl' ),
				'not_found'                  => __( 'No countries found.', 'pcl' ),
				'no_terms'                   => __( 'No countries', 'pcl' ),
			),
			'description'        => 'Countries should always be nested under the two character ISO 3166 country code to avoid issues where multiple countries have the same postal code.',
			'public'             => true,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => false,
			'show_admin_column'  => true,
			'rewrite'            => array(
				'slug' => apply_filters( 'pcl_country_tax_slug', 'country' ),
			),
			'query_var'          => 'countries',
			'show_in_rest'       => false,
		) );

		register_taxonomy( 'pcl_postal_code', pcl_get_enabled_post_types(), array(
			'labels'             => array(
				'name'                       => _x( 'Postal Codes', 'taxonomy general name', 'pcl' ),
				'singular_name'              => _x( 'Postal Code', 'taxonomy singular name', 'pcl' ),
				'search_items'               => __( 'Search Postal Codes', 'pcl' ),
				'popular_items'              => __( 'Popular Postal Codes', 'pcl' ),
				'all_items'                  => __( 'All Postal Codes', 'pcl' ),
				'edit_item'                  => __( 'Edit Postal Code', 'pcl' ),
				'view_item'                  => __( 'View Postal Code', 'pcl' ),
				'update_item'                => __( 'Update Postal Code', 'pcl' ),
				'add_new_item'               => __( 'Add New Postal Code', 'pcl' ),
				'new_item_name'              => __( 'New Postal Code Name', 'pcl' ),
				'separate_items_with_commas' => __( 'Separate postal codes with commas', 'pcl' ),
				'add_or_remove_items'        => __( 'Add or remove postal codes', 'pcl' ),
				'choose_from_most_used'      => __( 'Choose from the most used postal codes', 'pcl' ),
				'not_found'                  => __( 'No postal codes found.', 'pcl' ),
				'no_terms'                   => __( 'No postal codes', 'pcl' ),
			),
			'description'        => 'Postal Codes should always be nested under the two character ISO 3166 country code to avoid issues where multiple countries have the same postal code.',
			'public'             => true,
			'hierarchical'       => true,
			'query_var'          => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => false,
			'show_admin_column'  => false,
			'rewrite'            => false,
		) );
	}

	/**
	 * Terms should never really be edited, but let's clear cache in case.
	 *
	 * @param $term_id
	 * @param $tt_id
	 */
	function edited_pcl_postal_code( $term_id, $tt_id ) {
		$term = get_term( $term_id, 'pcl_postal_codes' );

		if ( $term && ! is_wp_error( $term ) ) {
			wp_cache_delete( strtoupper( $term->slug ), 'pcl_postal_codes' );
		}
	}

	/**
	 * Make sure to clean up the cache when a postal code term is deleted
	 *
	 * @param $term
	 * @param $tt_id
	 * @param $taxonomy
	 * @param $deleted_term
	 */
	function delete_pcl_postal_code( $term, $tt_id, $deleted_term ) {
		wp_cache_delete( strtoupper( $deleted_term->slug ), 'pcl_postal_codes' );
	}
}
$pcl_taxonomy = new PCL_Taxonomy();