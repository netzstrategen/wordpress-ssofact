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

    if (is_admin()) {
      return;
    }

    // Replaces the front-end login form of WooCommerce to submit to the SSO
    // server instead of WordPress. The administrative login on /wp-login.php is
    // not changed and still authenticates against the local WordPress site only.
    add_action('woocommerce_before_customer_login_form', __NAMESPACE__ . '\WooCommerce::woocommerce_before_customer_login_form');
    add_action('woocommerce_after_customer_login_form', __NAMESPACE__ . '\WooCommerce::woocommerce_after_customer_login_form');

    // Validates checkout fields against SSO.
    add_action('woocommerce_checkout_process', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_process');
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
