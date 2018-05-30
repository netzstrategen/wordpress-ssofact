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
  private static $baseUrl;

  /**
   * @implements plugins_loaded
   */
  public static function plugins_loaded() {
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
      $authorize_url = OpenID_Connect_Generic::getClient()->make_authentication_url();
      $target = '&' . http_build_query([
        'target' => $_SERVER['REQUEST_URI'],
      ]);
      $authorize_url = preg_replace('@&redirect_uri=[^&]+@', '$0' . $target, $authorize_url);
      if (wp_redirect($authorize_url, 307)) {
        exit();
      }
    }
    return $user_id;
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
