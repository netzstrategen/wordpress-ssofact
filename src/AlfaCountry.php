<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\AlfaCountry.
 */

namespace Netzstrategen\Ssofact;

/**
 * Alfamedia VM country code mapping.
 */
class AlfaCountry {

  /**
   * Maps WooCommerce/ISO country codes to Alfamedia VM country vehicle codes (LKZ).
   *
   * @see woocommerce/i18n/countries.php
   */
  const MAP = [
    'AF' => 'AFG', // Afghanistan
    'AX' => '', // &#197;land Islands
    'AL' => 'AL', // Albania
    'DZ' => 'DZ', // Algeria
    'AS' => '', // American Samoa
    'AD' => '', // Andorra
    'AO' => 'ANG', // Angola
    'AI' => '', // Anguilla
    'AQ' => '', // Antarctica
    'AG' => '', // Antigua and Barbuda
    'AR' => 'RA', // Argentina
    'AM' => 'ARM', // Armenia
    'AW' => '', // Aruba
    'AU' => 'AUS', // Australia
    'AT' => 'A', // Austria
    'AZ' => '', // Azerbaijan
    'BS' => '', // Bahamas
    'BH' => '', // Bahrain
    'BD' => '', // Bangladesh
    'BB' => '', // Barbados
    'BY' => 'BY', // Belarus
    'BE' => 'B', // Belgium
    'PW' => '', // Belau
    'BZ' => '', // Belize
    'BJ' => '', // Benin
    'BM' => '', // Bermuda
    'BT' => '', // Bhutan
    'BO' => 'BOL', // Bolivia
    'BQ' => '', // Bonaire, Saint Eustatius and Saba
    'BA' => 'BIH', // Bosnia and Herzegovina
    'BW' => '', // Botswana
    'BV' => '', // Bouvet Island
    'BR' => 'BR', // Brazil
    'IO' => '', // British Indian Ocean Territory
    'VG' => 'VG', // British Virgin Islands
    'BN' => '', // Brunei
    'BG' => 'BG', // Bulgaria
    'BF' => '', // Burkina Faso
    'BI' => '', // Burundi
    'KH' => '', // Cambodia
    'CM' => 'CAM', // Cameroon
    'CA' => 'CDN', // Canada
    'CV' => '', // Cape Verde
    'KY' => '', // Cayman Islands
    'CF' => '', // Central African Republic
    'TD' => '', // Chad
    'CL' => 'RCH', // Chile
    'CN' => 'RC', // China
    'CX' => '', // Christmas Island
    'CC' => '', // Cocos (Keeling) Islands
    'CO' => 'CO', // Colombia
    'KM' => '', // Comoros
    'CG' => '', // Congo (Brazzaville)
    'CD' => '', // Congo (Kinshasa)
    'CK' => '', // Cook Islands
    'CR' => 'CR', // Costa Rica
    'HR' => 'HR', // Croatia
    'CU' => 'CU', // Cuba
    'CW' => '', // Cura&ccedil;ao
    'CY' => 'CY', // Cyprus
    'CZ' => 'CZ', // Czech Republic
    'DK' => 'DK', // Denmark
    'DJ' => '', // Djibouti
    'DM' => '', // Dominica
    'DO' => '', // Dominican Republic
    'EC' => 'EC', // Ecuador
    'EG' => 'ET', // Egypt
    'SV' => '', // El Salvador
    'GQ' => '', // Equatorial Guinea
    'ER' => '', // Eritrea
    'EE' => 'EE', // Estonia
    'ET' => '', // Ethiopia
    'FK' => '', // Falkland Islands
    'FO' => '', // Faroe Islands
    'FJ' => '', // Fiji
    'FI' => 'FIN', // Finland
    'FR' => 'F', // France
    'GF' => '', // French Guiana
    'PF' => '', // French Polynesia
    'TF' => '', // French Southern Territories
    'GA' => '', // Gabon
    'GM' => '', // Gambia
    'GE' => '', // Georgia
    'DE' => 'D', // Germany
    'GH' => 'GH', // Ghana
    'GI' => 'GBZ', // Gibraltar
    'GR' => 'GR', // Greece
    'GL' => '', // Greenland
    'GD' => '', // Grenada
    'GP' => '', // Guadeloupe
    'GU' => '', // Guam
    'GT' => '', // Guatemala
    'GG' => '', // Guernsey
    'GN' => 'GN', // Guinea
    'GW' => '', // Guinea-Bissau
    'GY' => '', // Guyana
    'HT' => '', // Haiti
    'HM' => '', // Heard Island and McDonald Islands
    'HN' => '', // Honduras
    'HK' => '', // Hong Kong
    'HU' => 'H', // Hungary
    'IS' => 'IS', // Iceland
    'IN' => 'IND', // India
    'ID' => 'RI', // Indonesia
    'IR' => 'IR', // Iran
    'IQ' => 'IRQ', // Iraq
    'IE' => 'IRL', // Ireland
    'IM' => '', // Isle of Man
    'IL' => 'IL', // Israel
    'IT' => 'I', // Italy
    'CI' => 'CIV', // Ivory Coast
    'JM' => 'JA', // Jamaica
    'JP' => 'J', // Japan
    'JE' => '', // Jersey
    'JO' => '', // Jordan
    'KZ' => 'KZ', // Kazakhstan
    'KE' => 'EAK', // Kenya
    'KI' => '', // Kiribati
    'KW' => '', // Kuwait
    'KG' => 'KS', // Kyrgyzstan
    'LA' => '', // Laos
    'LV' => 'LV', // Latvia
    'LB' => 'RL', // Lebanon
    'LS' => '', // Lesotho
    'LR' => '', // Liberia
    'LY' => '', // Libya
    'LI' => 'FL', // Liechtenstein
    'LT' => 'LT', // Lithuania
    'LU' => 'L', // Luxembourg
    'MO' => '', // Macao S.A.R., China
    'MK' => 'MK', // Macedonia
    'MG' => '', // Madagascar
    'MW' => '', // Malawi
    'MY' => 'MAL', // Malaysia
    'MV' => '', // Maldives
    'ML' => '', // Mali
    'MT' => 'M', // Malta
    'MH' => 'MH', // Marshall Islands
    'MQ' => '', // Martinique
    'MR' => '', // Mauritania
    'MU' => '', // Mauritius
    'YT' => '', // Mayotte
    'MX' => 'MEX', // Mexico
    'FM' => '', // Micronesia
    'MD' => 'MD', // Moldova
    'MC' => 'MC', // Monaco
    'MN' => 'MNG', // Mongolia
    'ME' => 'MNE', // Montenegro
    'MS' => '', // Montserrat
    'MA' => 'MA', // Morocco
    'MZ' => '', // Mozambique
    'MM' => '', // Myanmar
    'NA' => 'NAM', // Namibia
    'NR' => '', // Nauru
    'NP' => 'NEP', // Nepal
    'NL' => 'NL', // Netherlands
    'NC' => '', // New Caledonia
    'NZ' => 'NZ', // New Zealand
    'NI' => '', // Nicaragua
    'NE' => '', // Niger
    'NG' => 'NIG', // Nigeria
    'NU' => '', // Niue
    'NF' => '', // Norfolk Island
    'MP' => '', // Northern Mariana Islands
    'KP' => 'PAT', // North Korea
    'NO' => 'N', // Norway
    'OM' => '', // Oman
    'PK' => 'PK', // Pakistan
    'PS' => '', // Palestinian Territory
    'PA' => 'PA', // Panama
    'PG' => 'PNG', // Papua New Guinea
    'PY' => 'PY', // Paraguay
    'PE' => 'PE', // Peru
    'PH' => 'RP', // Philippines
    'PN' => '', // Pitcairn
    'PL' => 'PL', // Poland
    'PT' => 'P', // Portugal
    'PR' => '', // Puerto Rico
    'QA' => '', // Qatar
    'RE' => '', // Reunion
    'RO' => 'RO', // Romania
    'RU' => 'RUS', // Russia
    'RW' => '', // Rwanda
    'BL' => '', // Saint Barth&eacute;lemy
    'SH' => '', // Saint Helena
    'KN' => '', // Saint Kitts and Nevis
    'LC' => '', // Saint Lucia
    'MF' => '', // Saint Martin (French part)
    'SX' => '', // Saint Martin (Dutch part)
    'PM' => '', // Saint Pierre and Miquelon
    'VC' => '', // Saint Vincent and the Grenadines
    'SM' => '', // San Marino
    'ST' => '', // S&atilde;o Tom&eacute; and Pr&iacute;ncipe
    'SA' => 'KSA', // Saudi Arabia
    'SN' => 'SEN', // Senegal
    'RS' => 'SRB', // Serbia
    'SC' => '', // Seychelles
    'SL' => '', // Sierra Leone
    'SG' => 'SGP', // Singapore
    'SK' => 'SK', // Slovakia
    'SI' => 'SLO', // Slovenia
    'SB' => '', // Solomon Islands
    'SO' => '', // Somalia
    'ZA' => 'ZA', // South Africa
    'GS' => '', // South Georgia/Sandwich Islands
    'KR' => 'KR', // South Korea
    'SS' => '', // South Sudan
    'ES' => 'E', // Spain
    'LK' => 'CL', // Sri Lanka
    'SD' => 'SUD', // Sudan
    'SR' => '', // Suriname
    'SJ' => '', // Svalbard and Jan Mayen
    'SZ' => '', // Swaziland
    'SE' => 'S', // Sweden
    'CH' => 'CH', // Switzerland
    'SY' => 'SYR', // Syria
    'TW' => '', // Taiwan
    'TJ' => '', // Tajikistan
    'TZ' => 'EAT', // Tanzania
    'TH' => 'T', // Thailand
    'TL' => '', // Timor-Leste
    'TG' => 'RT', // Togo
    'TK' => '', // Tokelau
    'TO' => '', // Tonga
    'TT' => '', // Trinidad and Tobago
    'TN' => 'TN', // Tunisia
    'TR' => 'TR', // Turkey
    'TM' => '', // Turkmenistan
    'TC' => '', // Turks and Caicos Islands
    'TV' => '', // Tuvalu
    'UG' => '', // Uganda
    'UA' => 'UA', // Ukraine
    'AE' => 'UAE', // United Arab Emirates
    'GB' => 'GB', // United Kingdom (UK)
    'US' => 'USA', // United States (US)
    'UM' => '', // United States (US) Minor Outlying Islands
    'VI' => '', // United States (US) Virgin Islands
    'UY' => 'ROU', // Uruguay
    'UZ' => 'UZ', // Uzbekistan
    'VU' => '', // Vanuatu
    'VA' => '', // Vatican
    'VE' => '', // Venezuela
    'VN' => 'VN', // Vietnam
    'WF' => '', // Wallis and Futuna
    'EH' => '', // Western Sahara
    'WS' => '', // Samoa
    'YE' => '', // Yemen
    'ZM' => '', // Zambia
    'ZW' => 'ZW', // Zimbabwe
  ];

  /**
   * Returns the alfa country vehicle code (if any) for a given ISO country code.
   *
   * @param string $iso_code
   *   The ISO country code to lookup.
   *
   * @return string
   */
  public static function toAlfa(string $iso_code) {
    return !empty(static::MAP[$iso_code]) ? static::MAP[$iso_code] : $iso_code;
  }

  /**
   * Returns the ISO country code (if any) for a given alfa country vehicle code.
   *
   * @param string $alfa_code
   *   The alfa country vehicle code to lookup.
   *
   * @return string
   */
  public static function toIso(string $alfa_code) {
    return array_search($alfa_code, static::MAP, TRUE) ?: $alfa_code;
  }

}
