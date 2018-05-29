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
   * @implements site_url
   */
  public static function site_url($url, $path, $scheme) {
    if ($path === '/openid-connect-authorize') {
      $url = strtr($url, ['/openid-connect-authorize' => '/shop/openid-connect/ssofact']);
    }
    return $url;
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
