<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\Plugin.
 */

namespace Netzstrategen\Ssofact;

use OpenID_Connect_Generic;

/**
 * Main front-end functionality.
 */
class Plugin {

  /**
   * Prefix for naming.
   *
   * @var string
   */
  const PREFIX = 'ssofact';

  /**
   * Gettext localization domain.
   *
   * @var string
   */
  const L10N = self::PREFIX;

  /**
   * @var string
   */
  const USER_META_USERINFO = 'openid-connect-generic-last-user-claim';

  /**
   * @var string
   */
  const ENDPOINT_AUTHORIZE = '/REST/oauth/authorize';
  const ENDPOINT_TOKEN = '/REST/oauth/access_token';
  const ENDPOINT_USERINFO = '/REST/oauth/user';
  const ENDPOINT_END_SESSION = '/REST/oauth/logout';

  /**
   * @var string
   */
  private static $baseUrl;

  /**
   * @implements plugins_loaded
   */
  public static function plugins_loaded() {
    // Populate openid-connect-generic settings if constants are defined in
    // wp-config.php.
    add_filter('option_openid_connect_generic_settings', __CLASS__ . '::option_openid_connect_generic_settings');
    add_filter('option_default_openid_connect_generic_settings', __CLASS__ . '::option_openid_connect_generic_settings');

    add_filter('site_url', __CLASS__ . '::site_url', 10, 3);

    // Automatically log in anonymous users having an SSO session cookie if all
    // other authentication methods did not result in an active user session.
    // (only possible if SSO server and client share a common domain)
    add_filter('determine_current_user', __CLASS__ . '::determine_current_user', 100);
  }

  /**
   * @implements init
   */
  public static function init() {
    static::redirectLogin();

    // @todo Use default URL instead as is it's covered by custom rewriterules already.
    add_rewrite_rule('^shop/openid-connect/ssofact/?', 'index.php?openid-connect-authorize=1', 'top');

    // Run after daggerhart-openid-connect-generic (99).
    add_filter('logout_redirect', __CLASS__ . '::logout_redirect', 100);

    // Update user profile meta data upon login.
    add_action('updated_user_meta', __CLASS__ . '::updated_user_meta', 10, 4);

    // Removes username field from checkout form (email is used as username).
    add_filter('option_woocommerce_registration_generate_username', function () { return 'yes'; });
    add_filter('option_default_woocommerce_registration_generate_username', function () { return 'yes'; });

    // Removes password field from registration form.
    add_filter('option_woocommerce_registration_generate_password', function () { return 'yes'; });
    add_filter('option_default_woocommerce_registration_generate_password', function () { return 'yes'; });

    // Defines default address fields for checkout and user account forms.
    // Sorts woocommerce default address fields array by priority.
    add_filter('woocommerce_default_address_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_default_address_fields');
    add_filter('woocommerce_customer_meta_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_customer_meta_fields');
    add_filter('woocommerce_default_address_fields', __NAMESPACE__ . '\WooCommerce::sortFieldsByPriority', 100);
    add_filter('woocommerce_checkout_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_fields');
    // Appends the house number to the billing/shipping address in thankyou page.
    add_filter('woocommerce_get_order_address', __NAMESPACE__ . '\WooCommerce::woocommerce_get_order_address', 10, 3);

    // Adds opt-in checkboxes to user account edit form.
    add_action('woocommerce_edit_account_form', __NAMESPACE__ . '\WooCommerce::woocommerce_edit_account_form');

    // Validates checkout fields against SSO.
    add_action('woocommerce_checkout_process', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_process', 20);

    // Validates and updates user info in SSO upon editing address.
    add_action('woocommerce_after_save_address_validation', __NAMESPACE__ . '\WooCommerce::woocommerce_after_save_address_validation', 10, 3);

    // Validate changed email address against SSO.
    add_action('woocommerce_save_account_details_errors', __NAMESPACE__ . '\WooCommerce::woocommerce_save_account_details_errors', 20, 2);
    // Updates user info in SSO upon editing account details.
    add_action('woocommerce_save_account_details', __NAMESPACE__ . '\WooCommerce::woocommerce_save_account_details');

    if (is_admin()) {
      return;
    }

    // Replaces the front-end login form of WooCommerce to submit to the SSO
    // server instead of WordPress. The administrative login on /wp-login.php is
    // not changed and still authenticates against the local WordPress site only.
    add_action('woocommerce_before_customer_login_form', __NAMESPACE__ . '\WooCommerce::woocommerce_before_customer_login_form');
    add_action('woocommerce_after_customer_login_form', __NAMESPACE__ . '\WooCommerce::woocommerce_after_customer_login_form');

    // Validate current password against SSO.
    add_action('check_password', __CLASS__ . '::check_password', 20, 4);
  }

  /**
   * @implements option_NAME
   * @implements option_default_NAME
   */
  public static function option_openid_connect_generic_settings($value) {
    // @todo Move into openid-connect-generic plugin.
    if (defined('OPENID_CONNECT_CLIENT_ID')) {
      $value['client_id'] = OPENID_CONNECT_CLIENT_ID;
    }
    if (defined('OPENID_CONNECT_CLIENT_SECRET')) {
      $value['client_secret'] = OPENID_CONNECT_CLIENT_SECRET;
    }
    if (defined('SSOFACT_SERVER_DOMAIN')) {
      $value['scope'] = '';
      $value['endpoint_login'] = 'https://' . SSOFACT_SERVER_DOMAIN . static::ENDPOINT_AUTHORIZE;
      $value['endpoint_token'] = 'https://' . SSOFACT_SERVER_DOMAIN . static::ENDPOINT_TOKEN;
      $value['endpoint_userinfo'] = 'https://' . SSOFACT_SERVER_DOMAIN . static::ENDPOINT_USERINFO;
      $value['endpoint_end_session'] = 'https://' . SSOFACT_SERVER_DOMAIN . static::ENDPOINT_END_SESSION;
      $value['identity_key'] = 'email';
      $value['nickname_key'] = 'email';
      $value['email_format'] = '{email}';
      $value['displayname_format'] = '{firstname} {lastname}';
      $value['identify_with_username'] = 0;
      $value['link_existing_users'] = 1;
      $value['redirect_user_back'] = 1;
      $value['redirect_on_logout'] = 1;
    }
    return $value;
  }

  /**
   * @implements site_url
   */
  public static function site_url($url, $path, $scheme) {
    if ($path === '/openid-connect-authorize') {
      $url = strtr($url, ['/openid-connect-authorize' => '/shop/openid-connect/ssofact']);
    }
    return $url;
  }

  /**
   * @implements init
   */
  public static function redirectLogin() {
    if ($GLOBALS['pagenow'] !== 'wp-login.php') {
      return;
    }
    if (!isset($_REQUEST['action']) || $_REQUEST['action'] === 'login') {
      $_REQUEST['redirect_to'] = wc_get_account_endpoint_url('dashboard');
    }
  }

  /**
   * @implements determine_current_user
   */
  public static function determine_current_user($user_id) {
    if ($user_id) {
      return $user_id;
    }
    // If there is a session cookie from the SSO server, attempt to authenticate
    // the client against it on the server, unless the current path is a
    // OpenID Connection endpoint.
    if (!empty($_COOKIE['RF_OAUTH_SERVER']) && FALSE === strpos($_SERVER['REQUEST_URI'], 'ssofact')) {
      $authorize_uri = static::getAuthorizeUrl();
      if (wp_redirect($authorize_uri, 307)) {
        exit();
      }
    }
    return $user_id;
  }

  /**
   * Returns the authorization URI, optionally with custom redirect destination.
   *
   * @param string $destination
   *   (optional) The path to redirect to after successful login.
   *
   * @return string
   *   The authorization URI to use for login redirects and form actions.
   */
  public static function getAuthorizeUrl($destination = '') {
    if (empty($destination)) {
      $destination = $_SERVER['REQUEST_URI'];
    }
    $authorize_uri = OpenID_Connect_Generic::getClient()->make_authentication_url();
    $destination = '&' . http_build_query([
      'target' => $destination,
    ]);
    $authorize_uri = preg_replace('@&redirect_uri=[^&]+@', '$0' . $destination, $authorize_uri);
    return $authorize_uri;
  }

  /**
   * @implements logout_redirect
   */
  public static function logout_redirect($url) {
    $url = strtr($url, [
      '/logout' => '/logout/' . OpenID_Connect_Generic::getSettings()->client_id,
      'post_logout_redirect_uri' => 'target',
    ]);
    return $url;
  }

  /**
   * @implements check_password
   */
  public static function check_password($result, $password, $hash, $user_id) {
    // Allow local user accounts to authenticate. This check should never
    // succeed for accounts authenticated via the SSO server, as their passwords
    // should not exist/match locally.
    if ($result === TRUE) {
      return $result;
    }
    $current_user = get_user_by('id', $user_id);
    $response = Server::validateLogin($current_user->user_email, $password);
    if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Updates local user meta data with UserInfo provided by SSO.
   *
   * @implements updated_{$meta_type}_meta
   */
  public static function updated_user_meta($meta_id, $user_id, $meta_key, $user_claims) {
    if ($meta_key !== Plugin::USER_META_USERINFO) {
      return;
    }
    update_user_meta($user_id, 'first_name', $user_claims['firstname']);
    update_user_meta($user_id, 'last_name', $user_claims['lastname']);

    update_user_meta($user_id, 'billing_salutation', $user_claims['salutation']);
    // update_user_meta($user_id, '', $user_claims['title']);
    update_user_meta($user_id, 'billing_first_name', $user_claims['firstname']);
    update_user_meta($user_id, 'billing_last_name', $user_claims['lastname']);
    update_user_meta($user_id, 'billing_company', $user_claims['company']);
    update_user_meta($user_id, 'billing_address_1', $user_claims['street']);
    update_user_meta($user_id, 'billing_house_number', $user_claims['housenr']);
    update_user_meta($user_id, 'billing_postcode', $user_claims['zipcode']);
    update_user_meta($user_id, 'billing_city', $user_claims['city']);
    update_user_meta($user_id, 'billing_country', $user_claims['country']);
    // update_user_meta($user_id, 'billing_state', $user_claims['']);
    update_user_meta($user_id, 'billing_phone', $user_claims['phone_prefix'] . '-' . $user_claims['phone']);
    update_user_meta($user_id, 'billing_email', $user_claims['email']);

    update_user_meta($user_id, 'subscriber_id', $user_claims['subscriber_id'] ?? $user_claims['subscribernr']);
    // update_user_meta($user_id, '', $user_claims['fcms_id']);
    // update_user_meta($user_id, '', $user_claims['facebook_id']);

    // update_user_meta($user_id, '', $user_claims['confirmed']);
    update_user_meta($user_id, 'last_update', $user_claims['lastchgdate']);

    // update_user_meta($user_id, 'roles', $user_claims['']);
    // update_user_meta($user_id, '', $user_claims['optins']); // array ( 'email_doi' => '0', 'list_premium' => '0', 'list_noch-fragen' => '0', 'list_freizeit' => '0', 'confirm_agb' => '0', 'acquisitionMail' => '0', 'acquisitionEmail' => '0', 'acquisitionPhone' => '0', 'changemail' => '0', )

    // wp_capabilities | {"administrator":true}                                                                                                                                 |
    // wp_user_level | 10
  }

  /**
   * Builds userinfo for updateUser, registerUser, and registerUserAndPurchase.
   *
   * @param int $user_id
   *   The user ID for which the generate the user info for. Defaults to the
   *   currently logged-in user.
   * @param string $key_prefix
   *   (optional) The prefix to use for looking up fields in the $_POST array;
   *   e.g., 'billing', 'shipping', or 'account'.
   */
  public static function buildUserInfo($user_id = 0, $key_prefix = 'billing') {
    $address_source = $_POST;

    if ($user_id < 1) {
      $user_id = get_current_user_ID();
    }
    if ($user_id) {
      $last_known_userinfo = get_user_meta($user_id, Plugin::USER_META_USERINFO, TRUE);
      if (empty($last_known_userinfo['id'])) {
        throw new \LogicException('Unable to build user info: Missing SSO ID.');
      }
      $userinfo = [
        'id' => $last_known_userinfo['id'],
        'email' => $last_known_userinfo['email'],
      ];
    }
    else {
      $userinfo = [
        'email' => $address_source[$key_prefix . '_email'],
      ];
    }

    // Handle billing/shipping address forms.
    if (isset($address_source[$key_prefix . '_address_1'])) {
      $phone = explode('-', $address_source[$key_prefix . '_phone'], 2);
      $userinfo += [
        'salutation' => $address_source[$key_prefix . '_salutation'],
        // 'title' => $address_source[$key_prefix . '_title'],
        'firstname' => $address_source[$key_prefix . '_first_name'],
        'lastname' => $address_source[$key_prefix . '_last_name'],
        'street' => $address_source[$key_prefix . '_address_1'],
        'housenr' => $address_source[$key_prefix . '_house_number'],
        'zipcode' => $address_source[$key_prefix . '_postcode'],
        'city' => $address_source[$key_prefix . '_city'],
        // @todo Implement proper mapping for country.
        'country' => 'DE', // $address_source[$key_prefix . '_country'],
        // 'birthday' => ,
        'phone_prefix' => $phone[0] ?? '',
        'phone' => $phone[1] ?? '',
      ];
    }

    $optin_source = $_POST;
    $terms_accepted = !empty($_POST['terms']);
    $userinfo += [
      'optins' => [
        'list_noch-fragen' => (int) !empty($optin_source['list_noch-fragen']),
        'list_premium' => (int) !empty($optin_source['list_premium']),
        'list_freizeit' => (int) !empty($optin_source['list_freizeit']),
        'confirm_agb' => (int) $terms_accepted,
        'acquisitionEmail' => (int) !empty($optin_source['confirm_agb']),
        'acquisitionMail' => (int) !empty($optin_source['confirm_mail']),
        'acquisitionPhone' =>(int) !empty($optin_source['confirm_phone']),
      ],
    ];
    return $userinfo;
  }

  /**
   * Builds purchase payload for registerPurchase and registerUserAndPurchase.
   *
   * @param int $user_id
   *   The user ID for which the generate the user info for. Defaults to the
   *   currently logged-in user.
   * @param string $sku
   *   The SKU to purchase.
   */
  public static function buildPurchaseInfo($user_id = 0, $sku = '') {
    $purchase = static::buildUserInfo($user_id);

    $sku = 'premiumAboNEU';
    $sku_parts = preg_split('@[:-]@', $sku);
    $accessType = $sku_parts[0];
    $edition = $sku_parts[1] ?? '';

    $purchase['permission'] = [
      'accessType' => $accessType,
      // 'type' => 'Product',
      // 'object' => 'Zeitung',
      'edition' => $edition,
      // @todo Custom delivery start date for print subscriptions.
      'fromDay' => date_i18n('Ymd'),
      // @todo Does the shop specify this freely on the client-side?
      // 'toDay' => date_i18n('Ymd', strtotime('plus 30 days')),
      'acquisitionEMail' => $purchase['optins']['acquisitionEmail'] ? 'j' : 'n',
      'acquisitionMail' => $purchase['optins']['acquisitionMail'] ? 'j' : 'n',
      'acquisitionPhone' => $purchase['optins']['acquisitionPhone'] ? 'j' : 'n',
      'paymentMethod' => '',
      'paymentPattern' => '',
      // 'accessCount' => 1,
      // 'promotionId' => '',
      // 'agentNumber' => '',
      // 'deviceType' => '', // Gerätetyp zB. iPhone9,3
    ];
    // Ensure that all values for the alfa purchase are strings.
    $purchase['permission'] = array_map('strval', $purchase['permission']);
    return $purchase;
  }

  /**
   * Loads the plugin textdomain.
   */
  public static function loadTextdomain() {
    load_plugin_textdomain(static::L10N, FALSE, static::L10N . '/languages/');
  }

  /**
   * The base URL path to this plugin's folder.
   *
   * Uses plugins_url() instead of plugin_dir_url() to avoid a trailing slash.
   */
  public static function getBaseUrl() {
    if (!isset(static::$baseUrl)) {
      static::$baseUrl = plugins_url('', static::getBasePath() . '/plugin.php');
    }
    return static::$baseUrl;
  }

  /**
   * The absolute filesystem base path of this plugin.
   *
   * @return string
   */
  public static function getBasePath() {
    return dirname(__DIR__);
  }

}
