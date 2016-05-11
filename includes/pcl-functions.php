<?php

/**
 * Retrieve and sanitize post type option.
 *
 * @return array
 */
function pcl_get_enabled_post_types() {
	return array_map( 'sanitize_text_field', get_option( 'pcl_post_types', array() ) );
}

/**
 * Checks a given post type for location support.
 *
 * @param $post_type
 *
 * @return bool
 */
function pcl_has_support( $post_type ) {
	return post_type_supports( $post_type, 'postal_code_locations' );
}

/**
 * Get a postal code object
 *
 * @param string $postal_code
 * @param string $country_code
 *
 * @return bool|null|PCL_Postal_Code
 */
function pcl_get_postal_code( $postal_code, $country_term ) {
	if ( $postal_code instanceof PCL_Postal_Code ) {
		$_postal_code = $postal_code;
	} elseif ( is_object( $postal_code ) ) {
		$_postal_code = PCL_Postal_Code::get_instance( $postal_code->postal_code, $postal_code->country_term );
	} else {
		$_postal_code = PCL_Postal_Code::get_instance( $postal_code, $country_term );
	}

	if ( ! $_postal_code ) {
		return null;
	}

	return $_postal_code;
}

/**
 * Wrapper function for calling get_term_by
 *
 * @param $field
 * @param $value
 * @param $taxonomy
 */
function pcl_postal_code_get_term_by( $field, $value ) {
	if ( function_exists( 'wpcom_vip_get_term_by' ) ) {
		return wpcom_vip_get_term_by( $field, $value, 'pcl_postal_code' );
	} else {
		return get_term_by( $field, $value, 'pcl_postal_code' );
	}
}

/**
 * Get the coordinate pair for a postal code
 *
 * @param $postal_code
 * @param $country_code
 *
 * @return array|null
 */
function pcl_postal_code_get_coordinates( $postal_code, $country_code ) {
	$postal_code = pcl_get_postal_code( $postal_code, $country_code );

	if ( empty( $postal_code ) ) {
		return null;
	}

	return array(
		'latitude' => $postal_code->latitude,
		'longitude' => $postal_code->longitude,
	);
}

/**
 * Attempts to correct some common mistakes and differences in how postal codes are entered for various countries to match what the Zippapotamus API expects to receive.
 * Because the API may expect a substring of the postal code, $snip is available to keep the full length of the postal code for profile editing purposes.
 *
 * @param $country_code
 * @param $postal_code
 * @param bool $snip
 *
 * @return mixed|string
 */
function pcl_validate_postal_code( $country_code, $postal_code, $snip = true ) {
	$new_postal_code = trim( $postal_code );

	/**
	 * Lengths of postal codes.
	 * This number will include any required spaces, hyphens, or prefixes because lengths are applied last.
	 */
	$lengths = array(
		9 => array(
			'BR',
		),
		8 => array(
			'PT',
			'JP',
		),
		7 => array(
			'MD',
		),
		6 => array(
			'IN',
			'RU',
			'PL',
			'CZ',
			'SK',
			'LU',
		),
		5 => array(
			'MX',
			'ES',
			'TR',
			'FR',
			'US',
			'IT',
			'DE',
			'PK',
			'MY',
			'LK',
			'TH',
			'GT',
			'DO',
			'MQ',
			'GP',
			'MC',
			'SM',
			'GF',
			'YT',
			'VI',
			'GY',
			'MP',
			'MH',
			'PM',
			'VA',
			'AS',
			'RE',
			'LT',
			'SE',
			'HR',
			'FI',
			'PR',
			'GU',
			'AD',
		),
		4 => array(
			'AU',
			'BG',
			'AT',
			'CH',
			'NO',
			'HU',
			'ZA',
			'BE',
			'PH',
			'NZ',
			'BD',
			'DK',
			'MK',
			'GL',
			'LI',
			'SJ',
			'AR',
			'SI',
			'GB',
			'NL',
		),
		3 => array(
			'CA',
			'IS',
			'FO',
			'IM',
			'GG',
			'JE',
		),
	);

	$only_numbers = array(
		'LT',
		'AR',
		'SE',
		'HR',
		'SI',
		'PR',
		'GU',
		'NL',
		'FI',
	);

	/**
	 * Countries that have hyphens in their postal code.
	 * Index is country code, value is the position of the hyphen
	 */
	$hyphenated_codes = array(
		'PL' => 3,
		'JP' => 4,
		'PT' => 5,
		'BR' => 6,
	);

	/**
	 * Countries that have spaces in their postal code.
	 * Index is country code, value is the position of the space
	 */
	$space_codes = array(
		'CZ' => 4,
		'SK' => 4,
	);

	/**
	 * Countries that require a prefix in their postal code.
	 * Index is country code, value the prefix
	 */
	$prefix_codes = array(
		'LU' => 'L-',
		'MD' => 'MD-',
		'IM' => 'IM',
		'GG' => 'GY',
		'AD' => 'AD',
		'JE' => 'JE',
	);

	//Strip all non numeric characters out for number only postal codes.
	if ( in_array( $country_code, $only_numbers ) ) {
		$new_postal_code = str_replace( array( '+', '-' ), '', filter_var( $postal_code, FILTER_SANITIZE_NUMBER_INT ) );
	}

	//For some Great Britain, we only need what comes before the first space.
	if ( 'GB' == $country_code && $snip ) {
		$parts = explode( ' ', $new_postal_code );
		$new_postal_code = $parts[0];
	}

	//Place hyphens in the appropriate spots
	if ( isset( $hyphenated_codes[ $country_code ] ) ) {
		if ( false === strpos( $new_postal_code, '-' ) ) {
			$new_postal_code = substr( $new_postal_code, 0, ( $hyphenated_codes[ $country_code ] - 1 ) ) . '-' . substr( $new_postal_code, ( $hyphenated_codes[ $country_code ] - 1 ) );
		}
	}

	//Place spaces in the appropriate spots
	if ( isset( $space_codes[ $country_code ] ) ) {
		if ( false === strpos( $new_postal_code, ' ' ) ) {
			$new_postal_code = substr( $new_postal_code, 0, ( $space_codes[ $country_code ] - 1 ) ) . ' ' . substr( $new_postal_code, ( $space_codes[ $country_code ] - 1 ) );
		}
	}

	//Ensure postal codes have prefixes, if needed
	if ( isset( $prefix_codes[ $country_code ] ) ) {
		if ( false === strpos( $new_postal_code, $prefix_codes[ $country_code ] ) ) {
			$new_postal_code = $prefix_codes[ $country_code ] . $new_postal_code;
		}
	}

	if ( $snip ) {
		//Snip our standard length postal codes
		foreach ( $lengths as $length => $countries ) {
			if ( in_array( $country_code, $countries ) ) {
				$new_postal_code = substr( $postal_code, 0, $length );

				break;
			}
		}
	}
	return trim( $new_postal_code );
}

/**
 * Returns an array of acceptable radius values.
 *
 * @return array
 */
function pcl_get_radiuses() {
	return apply_filters( 'pcl_radiuses', array(
		50,
		75,
		100,
		150,
		200,
		250,
		300,
	) );
}

/**
 * Retrieve a country code for a given country term.
 *
 * @param $term_id
 *
 * @return bool|mixed
 */
function pcl_get_country_code( $term ) {
	if ( $term instanceof WP_Term ) {
		$country = $term;
	} else {
		$country = get_term( (int) $term, 'pcl_country' );
	}

	if ( empty( $country ) || is_wp_error( $country ) ) {
		return false;
	}

	return get_term_meta( $country->term_id, '_pcl_country_code', true );
}

/**
 * Returns an array of countries with two letter code as index.
 *
 * @return array
 */
function pcl_get_countries() {
	return array(
		'AF' => __( 'Afghanistan', 'pcl' ),
		'AX' => __( 'Åland Islands', 'pcl' ),
		'AL' => __( 'Albania', 'pcl' ),
		'DZ' => __( 'Algeria', 'pcl' ),
		'AD' => __( 'Andorra', 'pcl' ),
		'AO' => __( 'Angola', 'pcl' ),
		'AI' => __( 'Anguilla', 'pcl' ),
		'AQ' => __( 'Antarctica', 'pcl' ),
		'AG' => __( 'Antigua and Barbuda', 'pcl' ),
		'AR' => __( 'Argentina', 'pcl' ),
		'AM' => __( 'Armenia', 'pcl' ),
		'AW' => __( 'Aruba', 'pcl' ),
		'AU' => __( 'Australia', 'pcl' ),
		'AT' => __( 'Austria', 'pcl' ),
		'AZ' => __( 'Azerbaijan', 'pcl' ),
		'BS' => __( 'Bahamas', 'pcl' ),
		'BH' => __( 'Bahrain', 'pcl' ),
		'BD' => __( 'Bangladesh', 'pcl' ),
		'BB' => __( 'Barbados', 'pcl' ),
		'BY' => __( 'Belarus', 'pcl' ),
		'BE' => __( 'Belgium', 'pcl' ),
		'PW' => __( 'Belau', 'pcl' ),
		'BZ' => __( 'Belize', 'pcl' ),
		'BJ' => __( 'Benin', 'pcl' ),
		'BM' => __( 'Bermuda', 'pcl' ),
		'BT' => __( 'Bhutan', 'pcl' ),
		'BO' => __( 'Bolivia', 'pcl' ),
		'BQ' => __( 'Bonaire, Saint Eustatius and Saba', 'pcl' ),
		'BA' => __( 'Bosnia and Herzegovina', 'pcl' ),
		'BW' => __( 'Botswana', 'pcl' ),
		'BV' => __( 'Bouvet Island', 'pcl' ),
		'BR' => __( 'Brazil', 'pcl' ),
		'IO' => __( 'British Indian Ocean Territory', 'pcl' ),
		'VG' => __( 'British Virgin Islands', 'pcl' ),
		'BN' => __( 'Brunei', 'pcl' ),
		'BG' => __( 'Bulgaria', 'pcl' ),
		'BF' => __( 'Burkina Faso', 'pcl' ),
		'BI' => __( 'Burundi', 'pcl' ),
		'KH' => __( 'Cambodia', 'pcl' ),
		'CM' => __( 'Cameroon', 'pcl' ),
		'CA' => __( 'Canada', 'pcl' ),
		'CV' => __( 'Cape Verde', 'pcl' ),
		'KY' => __( 'Cayman Islands', 'pcl' ),
		'CF' => __( 'Central African Republic', 'pcl' ),
		'TD' => __( 'Chad', 'pcl' ),
		'CL' => __( 'Chile', 'pcl' ),
		'CN' => __( 'China', 'pcl' ),
		'CX' => __( 'Christmas Island', 'pcl' ),
		'CC' => __( 'Cocos (Keeling) Islands', 'pcl' ),
		'CO' => __( 'Colombia', 'pcl' ),
		'KM' => __( 'Comoros', 'pcl' ),
		'CG' => __( 'Congo (Brazzaville)', 'pcl' ),
		'CD' => __( 'Congo (Kinshasa)', 'pcl' ),
		'CK' => __( 'Cook Islands', 'pcl' ),
		'CR' => __( 'Costa Rica', 'pcl' ),
		'HR' => __( 'Croatia', 'pcl' ),
		'CU' => __( 'Cuba', 'pcl' ),
		'CW' => __( 'Cura&Ccedil;ao', 'pcl' ),
		'CY' => __( 'Cyprus', 'pcl' ),
		'CZ' => __( 'Czech Republic', 'pcl' ),
		'DK' => __( 'Denmark', 'pcl' ),
		'DJ' => __( 'Djibouti', 'pcl' ),
		'DM' => __( 'Dominica', 'pcl' ),
		'DO' => __( 'Dominican Republic', 'pcl' ),
		'EC' => __( 'Ecuador', 'pcl' ),
		'EG' => __( 'Egypt', 'pcl' ),
		'SV' => __( 'El Salvador', 'pcl' ),
		'GQ' => __( 'Equatorial Guinea', 'pcl' ),
		'ER' => __( 'Eritrea', 'pcl' ),
		'EE' => __( 'Estonia', 'pcl' ),
		'ET' => __( 'Ethiopia', 'pcl' ),
		'FK' => __( 'Falkland Islands', 'pcl' ),
		'FO' => __( 'Faroe Islands', 'pcl' ),
		'FJ' => __( 'Fiji', 'pcl' ),
		'FI' => __( 'Finland', 'pcl' ),
		'FR' => __( 'France', 'pcl' ),
		'GF' => __( 'French Guiana', 'pcl' ),
		'PF' => __( 'French Polynesia', 'pcl' ),
		'TF' => __( 'French Southern Territories', 'pcl' ),
		'GA' => __( 'Gabon', 'pcl' ),
		'GM' => __( 'Gambia', 'pcl' ),
		'GE' => __( 'Georgia', 'pcl' ),
		'DE' => __( 'Germany', 'pcl' ),
		'GH' => __( 'Ghana', 'pcl' ),
		'GI' => __( 'Gibraltar', 'pcl' ),
		'GR' => __( 'Greece', 'pcl' ),
		'GL' => __( 'Greenland', 'pcl' ),
		'GD' => __( 'Grenada', 'pcl' ),
		'GP' => __( 'Guadeloupe', 'pcl' ),
		'GT' => __( 'Guatemala', 'pcl' ),
		'GG' => __( 'Guernsey', 'pcl' ),
		'GN' => __( 'Guinea', 'pcl' ),
		'GW' => __( 'Guinea-Bissau', 'pcl' ),
		'GY' => __( 'Guyana', 'pcl' ),
		'HT' => __( 'Haiti', 'pcl' ),
		'HM' => __( 'Heard Island and McDonald Islands', 'pcl' ),
		'HN' => __( 'Honduras', 'pcl' ),
		'HK' => __( 'Hong Kong', 'pcl' ),
		'HU' => __( 'Hungary', 'pcl' ),
		'IS' => __( 'Iceland', 'pcl' ),
		'IN' => __( 'India', 'pcl' ),
		'ID' => __( 'Indonesia', 'pcl' ),
		'IR' => __( 'Iran', 'pcl' ),
		'IQ' => __( 'Iraq', 'pcl' ),
		'IE' => __( 'Republic of Ireland', 'pcl' ),
		'IM' => __( 'Isle of Man', 'pcl' ),
		'IL' => __( 'Israel', 'pcl' ),
		'IT' => __( 'Italy', 'pcl' ),
		'CI' => __( 'Ivory Coast', 'pcl' ),
		'JM' => __( 'Jamaica', 'pcl' ),
		'JP' => __( 'Japan', 'pcl' ),
		'JE' => __( 'Jersey', 'pcl' ),
		'JO' => __( 'Jordan', 'pcl' ),
		'KZ' => __( 'Kazakhstan', 'pcl' ),
		'KE' => __( 'Kenya', 'pcl' ),
		'KI' => __( 'Kiribati', 'pcl' ),
		'KW' => __( 'Kuwait', 'pcl' ),
		'KG' => __( 'Kyrgyzstan', 'pcl' ),
		'LA' => __( 'Laos', 'pcl' ),
		'LV' => __( 'Latvia', 'pcl' ),
		'LB' => __( 'Lebanon', 'pcl' ),
		'LS' => __( 'Lesotho', 'pcl' ),
		'LR' => __( 'Liberia', 'pcl' ),
		'LY' => __( 'Libya', 'pcl' ),
		'LI' => __( 'Liechtenstein', 'pcl' ),
		'LT' => __( 'Lithuania', 'pcl' ),
		'LU' => __( 'Luxembourg', 'pcl' ),
		'MO' => __( 'Macao S.A.R., China', 'pcl' ),
		'MK' => __( 'Macedonia', 'pcl' ),
		'MG' => __( 'Madagascar', 'pcl' ),
		'MW' => __( 'Malawi', 'pcl' ),
		'MY' => __( 'Malaysia', 'pcl' ),
		'MV' => __( 'Maldives', 'pcl' ),
		'ML' => __( 'Mali', 'pcl' ),
		'MT' => __( 'Malta', 'pcl' ),
		'MH' => __( 'Marshall Islands', 'pcl' ),
		'MQ' => __( 'Martinique', 'pcl' ),
		'MR' => __( 'Mauritania', 'pcl' ),
		'MU' => __( 'Mauritius', 'pcl' ),
		'YT' => __( 'Mayotte', 'pcl' ),
		'MX' => __( 'Mexico', 'pcl' ),
		'FM' => __( 'Micronesia', 'pcl' ),
		'MD' => __( 'Moldova', 'pcl' ),
		'MC' => __( 'Monaco', 'pcl' ),
		'MN' => __( 'Mongolia', 'pcl' ),
		'ME' => __( 'Montenegro', 'pcl' ),
		'MS' => __( 'Montserrat', 'pcl' ),
		'MA' => __( 'Morocco', 'pcl' ),
		'MZ' => __( 'Mozambique', 'pcl' ),
		'MM' => __( 'Myanmar', 'pcl' ),
		'NA' => __( 'Namibia', 'pcl' ),
		'NR' => __( 'Nauru', 'pcl' ),
		'NP' => __( 'Nepal', 'pcl' ),
		'NL' => __( 'Netherlands', 'pcl' ),
		'AN' => __( 'Netherlands Antilles', 'pcl' ),
		'NC' => __( 'New Caledonia', 'pcl' ),
		'NZ' => __( 'New Zealand', 'pcl' ),
		'NI' => __( 'Nicaragua', 'pcl' ),
		'NE' => __( 'Niger', 'pcl' ),
		'NG' => __( 'Nigeria', 'pcl' ),
		'NU' => __( 'Niue', 'pcl' ),
		'NF' => __( 'Norfolk Island', 'pcl' ),
		'KP' => __( 'North Korea', 'pcl' ),
		'NO' => __( 'Norway', 'pcl' ),
		'OM' => __( 'Oman', 'pcl' ),
		'PK' => __( 'Pakistan', 'pcl' ),
		'PS' => __( 'Palestinian Territory', 'pcl' ),
		'PA' => __( 'Panama', 'pcl' ),
		'PG' => __( 'Papua New Guinea', 'pcl' ),
		'PY' => __( 'Paraguay', 'pcl' ),
		'PE' => __( 'Peru', 'pcl' ),
		'PH' => __( 'Philippines', 'pcl' ),
		'PN' => __( 'Pitcairn', 'pcl' ),
		'PL' => __( 'Poland', 'pcl' ),
		'PT' => __( 'Portugal', 'pcl' ),
		'PR' => __( 'Puerto Rico', 'pcl' ),
		'QA' => __( 'Qatar', 'pcl' ),
		'RE' => __( 'Reunion', 'pcl' ),
		'RO' => __( 'Romania', 'pcl' ),
		'RU' => __( 'Russia', 'pcl' ),
		'RW' => __( 'Rwanda', 'pcl' ),
		'BL' => __( 'Saint Barth&eacute;lemy', 'pcl' ),
		'SH' => __( 'Saint Helena', 'pcl' ),
		'KN' => __( 'Saint Kitts and Nevis', 'pcl' ),
		'LC' => __( 'Saint Lucia', 'pcl' ),
		'MF' => __( 'Saint Martin (French part)', 'pcl' ),
		'SX' => __( 'Saint Martin (Dutch part)', 'pcl' ),
		'PM' => __( 'Saint Pierre and Miquelon', 'pcl' ),
		'VC' => __( 'Saint Vincent and the Grenadines', 'pcl' ),
		'SM' => __( 'San Marino', 'pcl' ),
		'ST' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe', 'pcl' ),
		'SA' => __( 'Saudi Arabia', 'pcl' ),
		'SN' => __( 'Senegal', 'pcl' ),
		'RS' => __( 'Serbia', 'pcl' ),
		'SC' => __( 'Seychelles', 'pcl' ),
		'SL' => __( 'Sierra Leone', 'pcl' ),
		'SG' => __( 'Singapore', 'pcl' ),
		'SK' => __( 'Slovakia', 'pcl' ),
		'SI' => __( 'Slovenia', 'pcl' ),
		'SB' => __( 'Solomon Islands', 'pcl' ),
		'SO' => __( 'Somalia', 'pcl' ),
		'ZA' => __( 'South Africa', 'pcl' ),
		'GS' => __( 'South Georgia/Sandwich Islands', 'pcl' ),
		'KR' => __( 'South Korea', 'pcl' ),
		'SS' => __( 'South Sudan', 'pcl' ),
		'ES' => __( 'Spain', 'pcl' ),
		'LK' => __( 'Sri Lanka', 'pcl' ),
		'SD' => __( 'Sudan', 'pcl' ),
		'SR' => __( 'Suriname', 'pcl' ),
		'SJ' => __( 'Svalbard and Jan Mayen', 'pcl' ),
		'SZ' => __( 'Swaziland', 'pcl' ),
		'SE' => __( 'Sweden', 'pcl' ),
		'CH' => __( 'Switzerland', 'pcl' ),
		'SY' => __( 'Syria', 'pcl' ),
		'TW' => __( 'Taiwan', 'pcl' ),
		'TJ' => __( 'Tajikistan', 'pcl' ),
		'TZ' => __( 'Tanzania', 'pcl' ),
		'TH' => __( 'Thailand', 'pcl' ),
		'TL' => __( 'Timor-Leste', 'pcl' ),
		'TG' => __( 'Togo', 'pcl' ),
		'TK' => __( 'Tokelau', 'pcl' ),
		'TO' => __( 'Tonga', 'pcl' ),
		'TT' => __( 'Trinidad and Tobago', 'pcl' ),
		'TN' => __( 'Tunisia', 'pcl' ),
		'TR' => __( 'Turkey', 'pcl' ),
		'TM' => __( 'Turkmenistan', 'pcl' ),
		'TC' => __( 'Turks and Caicos Islands', 'pcl' ),
		'TV' => __( 'Tuvalu', 'pcl' ),
		'UG' => __( 'Uganda', 'pcl' ),
		'UA' => __( 'Ukraine', 'pcl' ),
		'AE' => __( 'United Arab Emirates', 'pcl' ),
		'GB' => __( 'United Kingdom', 'pcl' ),
		'US' => __( 'United States', 'pcl' ),
		'UY' => __( 'Uruguay', 'pcl' ),
		'UZ' => __( 'Uzbekistan', 'pcl' ),
		'VU' => __( 'Vanuatu', 'pcl' ),
		'VA' => __( 'Vatican', 'pcl' ),
		'VE' => __( 'Venezuela', 'pcl' ),
		'VN' => __( 'Vietnam', 'pcl' ),
		'WF' => __( 'Wallis and Futuna', 'pcl' ),
		'EH' => __( 'Western Sahara', 'pcl' ),
		'WS' => __( 'Western Samoa', 'pcl' ),
		'YE' => __( 'Yemen', 'pcl' ),
		'ZM' => __( 'Zambia', 'pcl' ),
		'ZW' => __( 'Zimbabwe', 'pcl' ),
	);
}