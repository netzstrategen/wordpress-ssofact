<?php

/*
  Plugin Name: ssoFACT
  Version: 1.1.0
  Text Domain: ssofact
  Description: Integrates ssoFACT into WordPress. Requires plugin daggerhart-openid-connect-generic.
  Author: netzstrategen
  Author URI: http://www.netzstrategen.com
  License: GPL-2.0+
  License URI: http://www.gnu.org/licenses/gpl-2.0
*/

namespace Netzstrategen\Ssofact;

if (!defined('ABSPATH')) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
  exit;
}

/**
 * Loads PSR-4-style plugin classes.
 */
function classloader($class) {
  static $ns_offset;
  if (strpos($class, __NAMESPACE__ . '\\') === 0) {
    if ($ns_offset === NULL) {
      $ns_offset = strlen(__NAMESPACE__) + 1;
    }
    include __DIR__ . '/src/' . strtr(substr($class, $ns_offset), '\\', '/') . '.php';
  }
}
spl_autoload_register(__NAMESPACE__ . '\classloader');

register_activation_hook(__FILE__, __NAMESPACE__ . '\Schema::activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\Schema::deactivate');
register_uninstall_hook(__FILE__, __NAMESPACE__ . '\Schema::uninstall');

add_action('plugins_loaded', __NAMESPACE__ . '\Plugin::loadTextdomain');
add_action('plugins_loaded', __NAMESPACE__ . '\Plugin::plugins_loaded');
// Run before OpenID_Connect_Generic::init() on /wp-login.php to adjust
// redirect_to of WooCommerce.
add_action('init', __NAMESPACE__ . '\Plugin::init', 9);
add_action('admin_init', __NAMESPACE__ . '\Admin::init');
