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

    // Increase timeout for requests to SSO server.
    // Run after the client wrapper added its settings (10).
    add_filter('openid-connect-generic-alter-request', __CLASS__ . '::alterOpenIdConnectRequest', 11, 2);

    // Add /shop prefix to openid-connect-generic client callback permalink.
    add_filter('site_url', __CLASS__ . '::site_url', 10, 3);

    // Automatically log in anonymous users having an SSO session cookie if all
    // other authentication methods did not result in an active user session.
    // (only possible if SSO server and client share a common domain)
    add_filter('determine_current_user', __CLASS__ . '::determine_current_user', 100);

    // Redirects user after OpenID Connect login.
    add_filter('openid-connect-generic-redirect-user-back', __CLASS__ . '::redirectAfterOpenIdConnectLogin', 10, 2);
    add_filter('wp_redirect', __CLASS__ . '::wp_redirect', 10, 2);

    // Update user profile meta data upon login.
    add_action('openid-connect-generic-login', __CLASS__ . '::onOpenIdConnectLogin', 10, 5);

    // Inject subscription status into WooCommerce Subcriptions.
    add_filter('wcs_user_has_subscription', __NAMESPACE__ . '\WooCommerce::wcs_user_has_subscription', 10, 4);

    // Register new email notification upon customer billing/shipping address change.
    add_filter('woocommerce_email_actions', __NAMESPACE__ . '\WooCommerce::woocommerce_email_actions');
    add_filter('woocommerce_email_classes', __NAMESPACE__ . '\WooCommerce::woocommerce_email_classes');

    // Disable automatic registration and login of newly registered user in checkout.
    // woocommerce_checkout_registration_enabled also disables the
    // .woocommerce-account-fields section in the checkout form.
    add_filter('woocommerce_checkout_registration_enabled', '__return_false', 100);
    // add_filter('woocommerce_checkout_registration_required', '__return_false', 100);
    add_filter('woocommerce_registration_auth_new_customer', '__return_false');
    add_action('woocommerce_created_customer', __NAMESPACE__ . '\WooCommerce::woocommerce_created_customer');
  }

  /**
   * @implements init
   */
  public static function preInit() {
    // Add query_var for inbound SSO server event callbacks.
    // @see Plugin::rewrite_rules_array(), Plugin::parse_request()
    add_rewrite_tag('%openid-connect-event%', '([^&]+)');

    static::redirectLogin();
  }

  /**
   * @implements init
   */
  public static function init() {
    // @todo Use default URL instead as is it's covered by custom rewriterules already.
    add_rewrite_rule('^shop/openid-connect/ssofact/?', 'index.php?openid-connect-authorize=1', 'top');

    // Add route for inbound SSO server event callbacks.
    add_filter('rewrite_rules_array', __CLASS__ . '::rewrite_rules_array');
    add_filter('parse_request', __CLASS__ . '::parse_request');

    // Run after daggerhart-openid-connect-generic (99).
    add_filter('logout_redirect', __CLASS__ . '::logout_redirect', 100);

    // Remove username and password fields from checkout form (email is username).
    // @see WooCommerce::woocommerce_checkout_fields()
    add_filter('option_woocommerce_registration_generate_username', function () { return 'yes'; });
    add_filter('option_woocommerce_registration_generate_password', function () { return 'yes'; });
    add_filter('option_default_woocommerce_registration_generate_username', function () { return 'yes'; });
    add_filter('option_default_woocommerce_registration_generate_password', function () { return 'yes'; });

    // Reduce minimum password strength to match approximately the current
    // (relaxed) definition for front-end users (Minimum 6 chars, at least 1 digit).
    add_filter('woocommerce_min_password_strength', function () { return 3; });

    // Disable core account change emails.
    add_filter('option_registrationnotification', function () { return 'no'; });
    add_filter('option_default_registrationnotification', function () { return 'no'; });
    add_filter('send_password_change_email', '__return_false');
    add_filter('send_email_change_email', '__return_false');
    add_filter('send_site_admin_email_change_email', '__return_false');
    add_filter('send_network_admin_email_change_email', '__return_false');
    add_filter('wpmu_signup_blog_notification', '__return_false');
    add_filter('wpmu_signup_user_notification', '__return_false');
    add_filter('wpmu_welcome_notification', '__return_false');
    add_filter('wpmu_welcome_user_notification', '__return_false');
    // Disable WooCommerce customer/account emails.
    add_action('woocommerce_email', __NAMESPACE__ . '\WooCommerce::woocommerce_email');

    // Defines default address fields for checkout and user account forms.
    // Sorts woocommerce default address fields array by priority.
    add_filter('woocommerce_default_address_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_default_address_fields');
    add_filter('woocommerce_customer_meta_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_customer_meta_fields');
    add_filter('woocommerce_default_address_fields', __NAMESPACE__ . '\WooCommerce::sortFieldsByPriority', 100);
    add_filter('woocommerce_checkout_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_fields');
    add_filter('woocommerce_billing_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_billing_fields');
    add_action('woocommerce_before_checkout_billing_form', 'ob_start', 0, 0);
    add_action('woocommerce_after_checkout_billing_form', __NAMESPACE__ . '\WooCommerce::woocommerce_after_checkout_billing_form');
    add_action('woocommerce_before_edit_address_form_billing', 'ob_start', 0, 0);
    add_action('woocommerce_after_edit_address_form_billing', __NAMESPACE__ . '\WooCommerce::woocommerce_after_checkout_billing_form');
    add_filter('woocommerce_shipping_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_shipping_fields');

    // Adds the account login/register form elements to the checkout form.
    add_action('woocommerce_checkout_billing', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_billing', 0);

    // Disable WooCommerce German Market confirmation form manipulation if this
    // is a multistep checkout form submission.
    if (!empty($_POST['step']) && !empty($_POST['woocommerce_checkout_update_totals'])) {
      add_filter('gm_checkout_validation_first_checkout', '__return_true');
      // @todo Find a better solution to disable WGM validation of 'terms'.
      $_POST['terms'] = 1;
    }

    // Saves and loads custom address field values to the user session.
    add_action('woocommerce_checkout_process', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_process');
    add_filter('woocommerce_checkout_get_value', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_get_value', 10, 2);
    add_filter('gm_sepa_fields_in_checkout', __NAMESPACE__ . '\WooCommerce::gm_sepa_fields_in_checkout');

    // Removes "Billing" field label prefix from error messages for some fields.
    add_filter('woocommerce_form_field_args', __NAMESPACE__ . '\WooCommerce::woocommerce_form_field_args');
    add_filter('woocommerce_checkout_required_field_notice', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_required_field_notice');
    add_filter('woocommerce_add_error', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_required_field_notice');

    // Validates and updates user info in SSO upon editing address.
    add_action('woocommerce_after_save_address_validation', __NAMESPACE__ . '\WooCommerce::woocommerce_after_save_address_validation', 10, 3);

    // Adds salutation, house number, phone_prefix to address output.
    add_filter('woocommerce_localisation_address_formats', __NAMESPACE__ . '\WooCommerce::woocommerce_localisation_address_formats');
    add_filter('woocommerce_formatted_address_replacements', __NAMESPACE__ . '\WooCommerce::woocommerce_formatted_address_replacements', 10, 2);
    add_filter('woocommerce_formatted_address_force_country_display', '__return_true');
    add_filter('woocommerce_customer_get_billing', __NAMESPACE__ . '\WooCommerce::woocommerce_customer_get_address', 10, 2);
    add_filter('woocommerce_customer_get_shipping', __NAMESPACE__ . '\WooCommerce::woocommerce_customer_get_address', 10, 2);
    add_filter('woocommerce_order_get_billing', __NAMESPACE__ . '\WooCommerce::woocommerce_customer_get_address', 10, 2);
    add_filter('woocommerce_order_get_shipping', __NAMESPACE__ . '\WooCommerce::woocommerce_customer_get_address', 10, 2);
    add_filter('woocommerce_order_get_billing_phone', __NAMESPACE__ . '\WooCommerce::woocommerce_order_get_billing_phone', 10, 2);

    // Removes core profile fields from account edit form.
    // Adds opt-in checkboxes to user account edit form.
    add_action('woocommerce_edit_account_form_start', 'ob_start', 0, 0);
    add_action('woocommerce_edit_account_form', __NAMESPACE__ . '\WooCommerce::woocommerce_edit_account_form');
    add_filter('woocommerce_save_account_details_required_fields', __NAMESPACE__ . '\WooCommerce::woocommerce_save_account_details_required_fields');

    // Add acquisition opt-in to checkout confirmation page.
    add_action('woocommerce_de_add_review_order', __NAMESPACE__ . '\WooCommerce::woocommerce_de_add_review_order');

    // Validates checkout fields against SSO.
    // Run before WGM_Template::do_de_checkout_after_validation() [priority 1]
    add_action('woocommerce_after_checkout_validation', __NAMESPACE__ . '\WooCommerce::woocommerce_after_checkout_validation', 0, 2);
    add_action('woocommerce_after_checkout_validation', __NAMESPACE__ . '\WooCommerce::woocommerce_after_checkout_validation_multistep', 0, 2);

    // Submits the order to the SSO/alfa.
    add_action('woocommerce_checkout_order_processed', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_order_processed', 20, 3);

    // Display custom fields in order totals and notification email.
    add_action('woocommerce_checkout_update_order_meta', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_update_order_meta');
    add_filter('woocommerce_get_order_item_totals', __NAMESPACE__ . '\WooCommerce::woocommerce_get_order_item_totals', 10, 3);
    add_action('woocommerce_email_order_meta', __NAMESPACE__ . '\WooCommerce::woocommerce_email_order_meta');

    // Display the selected payment interval value in the order confirmation page.
    add_action('woocommerce_checkout_before_customer_details', 'ob_start', 0, 0);
    add_action('woocommerce_checkout_after_customer_details', __NAMESPACE__ . '\WooCommerce::woocommerce_checkout_after_customer_details');

    // Validate changed email address against SSO.
    add_action('woocommerce_save_account_details_errors', __NAMESPACE__ . '\WooCommerce::woocommerce_save_account_details_errors', 20, 2);
    // Updates user info in SSO upon editing account details.
    add_action('woocommerce_save_account_details', __NAMESPACE__ . '\WooCommerce::woocommerce_save_account_details');
    // Do not redirect to dashboard after saving account details.
    add_action('woocommerce_save_account_details', __NAMESPACE__ . '\WooCommerce::woocommerce_save_account_details_redirect', 100);

    // Send redirect URL to forgot-password form on SSO.
    add_action('woocommerce_before_template_part', __NAMESPACE__ . '\WooCommerce::woocommerce_before_template_part', 10, 4);
    add_action('woocommerce_lostpassword_form', __NAMESPACE__ . '\WooCommerce::woocommerce_lostpassword_form');

    // Output current alfa purchases on subscriptions page of user account.
    add_action('woocommerce_after_template_part', __NAMESPACE__ . '\WooCommerce::woocommerce_after_template_part');

    // Skip nonce check for user logout.
    add_action('check_admin_referer', __CLASS__ . '::check_admin_referer', 10, 2);

    if (is_admin()) {
      return;
    }

    // Replaces the front-end login form of WooCommerce to submit to the SSO
    // server instead of WordPress. The administrative login on /wp-login.php is
    // not changed and still authenticates against the local WordPress site only.
    add_action('woocommerce_before_customer_login_form', 'ob_start', 0, 0);
    add_action('woocommerce_after_customer_login_form', __NAMESPACE__ . '\WooCommerce::woocommerce_after_customer_login_form');
    if (!is_user_logged_in()) {
      add_action('woocommerce_before_checkout_form', 'ob_start', 0, 0);
      add_action('woocommerce_login_form_end', __NAMESPACE__ . '\WooCommerce::woocommerce_login_form_end');
    }

    // Validate current password against SSO.
    add_action('check_password', __CLASS__ . '::check_password', 20, 4);

    // If user is already logged in and attempts to reset their password, the
    // current password is always correct.
    if (!empty($_POST['action']) && $_POST['action'] === 'save_account_details' && get_current_user_ID() && Plugin::getPasswordResetToken()) {
      $_POST['password_current'] = 'forgot-password-current-is-always-correct';
    }

    // Output current alfa purchases on subscriptions page of user account. (WIP)
    add_action('woocommerce_account_view-subscription_endpoint', __NAMESPACE__ . '\WooCommerce::viewSubscription', 9);

    add_action('wp_enqueue_scripts', __CLASS__ . '::wp_enqueue_scripts');
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
   * @implements openid-connect-generic-alter-request
   */
  public static function alterOpenIdConnectRequest(array $request, $op) {
    $request['timeout'] = 30;
    return $request;
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
   * @implements openid-connect-generic-redirect-user-back
   */
  public static function redirectAfterOpenIdConnectLogin($redirect_url, $user) {
    if (isset($_GET['target'])) {
      $redirect_url = site_url($_GET['target']);
    }
    return $redirect_url;
  }

  /**
   * @implements wp_redirect
   */
  public static function wp_redirect($redirect_url, $status) {
    if (isset($_GET['target'])) {
      $redirect_url = site_url($_GET['target']);
    }
    return $redirect_url;
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
   * @implements rewrite_rules_array
   */
  public static function rewrite_rules_array(array $rules) {
    $rules = ['^shop/openid-connect/ssofact/(.+)/?' => 'index.php?openid-connect-event=$matches[1]'] + $rules;
    return $rules;
  }

  /**
   * Handles inbound SSO server event requests.
   *
   * @implements parse_request
   */
  public static function parse_request($query) {
    if (isset($query->query_vars['openid-connect-event'])) {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_die('Invalid HTTP method.', 400);
      }
      // @todo Validate origin/authencity of SSO server.

      switch ($query->query_vars['openid-connect-event']) {
        case 'logout':
          $user = \OpenID_Connect_Generic::getClientWrapper()->get_user_by_identity($_POST['id']);
          if (!$user) {
            wp_die('User not found.', 404);
          }
          $manager = \WP_Session_Tokens::get_instance($user->ID);
          $manager->destroy_all();
          break;

        default:
          wp_die('Unsupported event name.', 400);
      }
      exit();
    }
  }

  /**
   * @implements logout_redirect
   */
  public static function logout_redirect($url) {
    $url = strtr($url, [
      '/logout' => '/logout/' . OpenID_Connect_Generic::getSettings()->client_id,
      'post_logout_redirect_uri' => 'redirect_uri',
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
    // If user is already logged in and attempts to reset their password, the
    // current password is always correct.
    if (get_current_user_ID() && Plugin::getPasswordResetToken()) {
      return TRUE;
    }
    $current_user = get_user_by('id', $user_id);
    $response = Server::validateLogin($current_user->user_email, $password);
    Server::addDebugMessage();
    if (isset($response['statuscode']) && $response['statuscode'] === 200) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Updates local user meta data with UserInfo provided by SSO.
   *
   * @implements openid-connect-generic-login
   */
  public static function onOpenIdConnectLogin($user, $token_response, $id_token_claim, $user_claims, $subject_identity) {
    $user_id = $user->ID;

    // Synchronize email address in user account.
    if ($user->user_email !== $user_claims['email']) {
      wp_update_user([
        'ID' => $user->ID,
        'user_email' => $user_claims['email'],
      ]);
    }
    // billing_email is only kept in sync but not displayed or used.
    update_user_meta($user_id, 'billing_email', $user_claims['email']);
    // update_user_meta($user_id, '', $user_claims['fcms_id']);
    // update_user_meta($user_id, '', $user_claims['facebook_id']);

    $subscriber_id = $user_claims['subscriber_id'] ?? $user_claims['subscribernr'];
    update_user_meta($user_id, 'billing_subscriber_id', $subscriber_id);
    update_user_meta($user_id, 'alfa_purchases', $user_claims['alfa_purchases']);

    // A user_status 'confirmed' (active/blocked) is not supported by WordPress
    // Core; the database table column exists but is unused. However, the user
    // will not be able to log in in the first place.
    if ($subscriber_id) {
      $user->add_role('customer');
      $purchases = Alfa::getPurchases($user_claims['alfa_purchases']);
      update_user_meta($user_id, 'paying_customer', (int) !empty($purchases));
    }
    else {
      $user->remove_role('customer');
      update_user_meta($user_id, 'paying_customer', 0);
    }
    // update_user_meta($user_id, 'roles', $user_claims['']);

    update_user_meta($user_id, 'optins', $user_claims['optins']);

    // User profile data may only be updated if the UserInfo from the SSO is
    // newer than our local data.
    $last_edit = get_user_meta($user_id, 'last_update', TRUE);
    if ($last_edit && $last_edit > $user_claims['profile_update_date']) {
      return;
    }
    update_user_meta($user_id, 'first_name', $user_claims['firstname']);
    update_user_meta($user_id, 'last_name', $user_claims['lastname']);

    $address_type = 'shipping';

    update_user_meta($user_id, $address_type . '_salutation', $user_claims['salutation']);
    // update_user_meta($user_id, '', $user_claims['title']);
    update_user_meta($user_id, $address_type . '_first_name', $user_claims['firstname']);
    if ($user_claims['salutation'] === 'Firma') {
      update_user_meta($user_id, $address_type . '_last_name', '');
      update_user_meta($user_id, $address_type . '_company_contact', $user_claims['lastname']);
    }
    else {
      update_user_meta($user_id, $address_type . '_last_name', $user_claims['lastname']);
      update_user_meta($user_id, $address_type . '_company_contact', '');
    }
    update_user_meta($user_id, $address_type . '_company', $user_claims['company']);
    update_user_meta($user_id, $address_type . '_address_1', $user_claims['street']);
    update_user_meta($user_id, $address_type . '_house_number', $user_claims['housenr']);
    update_user_meta($user_id, $address_type . '_postcode', $user_claims['zipcode']);
    update_user_meta($user_id, $address_type . '_city', $user_claims['city']);
    // update_user_meta($user_id, $address_type . '_state', $user_claims['']);
    if (!empty($user_claims['country'])) {
      $user_claims['country'] = AlfaCountry::toIso($user_claims['country']);
    }
    update_user_meta($user_id, $address_type . '_country', $user_claims['country']);

    update_user_meta($user_id, 'billing_phone_prefix', $user_claims['phone_prefix']);
    update_user_meta($user_id, 'billing_phone', $user_claims['phone']);

    update_user_meta($user_id, 'billing_iban', $user_claims['iban']);

    // Take over the new modification timestamp from the SSO.
    update_user_meta($user_id, 'last_update', $user_claims['profile_update_date']);
  }

 /**
  * @implements check_admin_referer
  */
  public static function check_admin_referer($action, $result) {
    if ($action === 'log-out') {
      // Borrowed code from wp-login.php.
      $user = wp_get_current_user();

      wp_logout();

      if ( ! empty( $_REQUEST['redirect_to'] ) ) {
        $redirect_to = $requested_redirect_to = $_REQUEST['redirect_to'];
      } else {
        $redirect_to = 'wp-login.php?loggedout=true';
        $requested_redirect_to = '';
      }

      if ( $switched_locale ) {
        restore_previous_locale();
      }

      /**
       * Filters the log out redirect URL.
       *
       * @since 4.2.0
       *
       * @param string  $redirect_to           The redirect destination URL.
       * @param string  $requested_redirect_to The requested redirect destination URL passed as a parameter.
       * @param WP_User $user                  The WP_User object for the user that's logging out.
       */
      $redirect_to = apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $user );
      wp_safe_redirect( $redirect_to );
      exit();
    }
  }

  /**
   * Builds userinfo for updateUser, registerUser, and registerUserAndPurchase.
   *
   * @param string $key_prefix
   *   (optional) The prefix to use for looking up fields in the $_POST array;
   *   e.g., 'billing', 'shipping', or 'account'. Defaults to 'billing'.
   * @param int $user_id
   *   The user ID for which the generate the user info for. Defaults to the
   *   currently logged-in user.
   */
  public static function buildUserInfo($key_prefix = 'billing', $user_id = NULL) {
    $address_source = $_POST;

    if (!isset($user_id)) {
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
        'email' => $address_source['billing_email'],
      ];
    }

    // Handle billing/shipping address forms.
    if (isset($address_source[$key_prefix . '_salutation'])) {
      if ($address_source[$key_prefix . '_salutation'] === 'Firma') {
        $userinfo += [
          'salutation' => $address_source[$key_prefix . '_salutation'],
          'company' => $address_source[$key_prefix . '_company'],
          'lastname' => $address_source[$key_prefix . '_company_contact'],
        ];
      }
      else {
        $userinfo += [
          'salutation' => $address_source[$key_prefix . '_salutation'],
          'firstname' => $address_source[$key_prefix . '_first_name'],
          'lastname' => $address_source[$key_prefix . '_last_name'],
        ];
      }
    }
    if (isset($address_source[$key_prefix . '_address_1'])) {
      $userinfo += [
        // 'title' => $address_source[$key_prefix . '_title'],
        'street' => $address_source[$key_prefix . '_address_1'],
        'housenr' => $address_source[$key_prefix . '_house_number'],
        'zipcode' => $address_source[$key_prefix . '_postcode'],
        'city' => $address_source[$key_prefix . '_city'],
        'country' => AlfaCountry::toAlfa($address_source[$key_prefix . '_country']),
        // 'birthday' => ,
      ];
    }
    if (isset($address_source['billing_phone'])) {
      $userinfo += [
        'phone_prefix' => $address_source['billing_phone_prefix'],
        'phone' => $address_source['billing_phone'],
      ];
      // @todo Web user account only supports a single phone number (due to data
      //   privacy and because landline numbers are a thing of the past). Migrate
      //   these to phone upon login/import.
      // unset($userinfo['mobile_prefix']);
      // unset($userinfo['mobile']);
    }

    // If a registered user has a subscriber ID already, then alfa GP/VM will
    // reject any kind of change to the address. The submitted address will only
    // be contained in the order confirmation email and manually processed by
    // the customer service team. Even if the user only specified the ID in the
    // checkout form, it has already been validated to be correct by now.
    if (is_checkout()) {
      $remove_address = $user_id > 0 && (!empty($address_source['billing_subscriber_id']) || get_user_meta($user_id, 'billing_subscriber_id', TRUE));
    }
    else {
      $remove_address = $user_id > 0 && get_user_meta($user_id, 'billing_subscriber_id', TRUE);
    }
    if ($remove_address) {
      $userinfo = array_diff_key($userinfo, [
        'salutation' => 0,
        'company' => 0,
        'title' => 0,
        'firstname' => 0,
        'lastname' => 0,
        'street' => 0,
        'housenr' => 0,
        'zipcode' => 0,
        'city' => 0,
        'country' => 0,
        'birthday' => 0,
        'phone_prefix' => 0,
        'phone' => 0,
      ]);
    }

    $optin_source = $_POST;
    if (!empty($_POST['terms'])) {
      $optin_source['confirm_agb'] = 1;
    }
    $userinfo['optins'] = $last_known_userinfo['optins'] ?? [];

    $wgm_optins = [
      'confirm_agb' => [],
    ];
    $optins = array_merge($wgm_optins, WooCommerce::OPTINS);
    foreach ($optins as $key => $definition) {
      if (isset($optin_source[$key])) {
        $userinfo['optins'][$key] = (int) !empty($optin_source[$key]);
      }
    }
    // Remove opt-ins that cannot be changed by the client.
    unset($userinfo['optins']['email_doi'], $userinfo['optins']['changemail']);

    return $userinfo;
  }

  /**
   * Builds purchase payload for registerPurchase and registerUserAndPurchase.
   *
   * @param string $sku
   *   The SKU to purchase.
   * @param string $key_prefix
   *   (optional) The prefix to use for looking up fields in the $_POST array;
   *   e.g., 'billing', 'shipping', or 'account'.
   * @param int $user_id
   *   The user ID for which the generate the user info for. Defaults to the
   *   currently logged-in user.
   */
  public static function buildPurchaseInfo($sku, $key_prefix = 'billing', $user_id = NULL) {
    $purchase = static::buildUserInfo($key_prefix, $user_id);

    $sku_parts = preg_split('@[:-]@', $sku);
    $accessType = $sku_parts[0];
    $edition = $sku_parts[1] ?? '';

    $purchase['permission'] = [
      'accessType' => $accessType,
      // 'type' => 'Product',
      // 'object' => 'Zeitung',
      'edition' => $edition,
      // Payment method options:
      // - german_market_purchase_on_account => 'r' (invoice)
      // - german_market_sepa_direct_debit => 'a' (direct debit)
      'paymentMethod' => isset($_POST['payment_method']) && $_POST['payment_method'] === 'german_market_sepa_direct_debit' ? 'a' : 'r',
      // Payment schedule options:
      // - 'm': monthly
      // - 'v': quarterly
      // - 'h': half-yearly
      // - 'j': yearly
      'paymentPattern' => $_POST['payment_interval'] ?? 'm',
      'acquisitionEmail' => !empty($purchase['optins']['acquisitionEmail']) ? 'j' : 'n',
      'acquisitionMail' => !empty($purchase['optins']['acquisitionMail']) ? 'j' : 'n',
      'acquisitionPhone' => !empty($purchase['optins']['acquisitionPhone']) ? 'j' : 'n',
      // 'accessCount' => 1,
      // 'promotionId' => '',
      // 'agentNumber' => '',
      // 'deviceType' => '', // Gerätetyp zB. iPhone9,3
    ];
    if ($edition === '') {
      unset($purchase['permission']['edition']);
    }
    // For regional variations of epapers, send the product (object).
    elseif (stripos($edition, 'e') === 0) {
      $purchase['permission']['object'] = 'EST';
    }
    // Optional custom delivery start date for print subscriptions.
    if (!empty($_POST['h_deliverydate'])) {
      // Convert 'j-n-Y' into 'Ymd' (with leading zeros).
      $purchase['permission']['fromDay'] = vsprintf('%3$04d%2$02d%1$02d', explode('-', $_POST['h_deliverydate']));
    }
    if (Alfa::isAccessTypeWeb($accessType)) {
      $purchase['optins']['list_redaktion-stimmede-editorial-premium-daily'] = 1;
    }

    // Ensure that all values for the alfa purchase are strings.
    $purchase['permission'] = array_map('strval', $purchase['permission']);
    return $purchase;
  }

  /**
   * Returns the password reset token from userinfo, if any.
   *
   * @return string
   *   The password reset token, if any.
   */
  public static function getPasswordResetToken() {
    $user_id = get_current_user_ID();
    $last_known_userinfo = get_user_meta($user_id, Plugin::USER_META_USERINFO, TRUE);
    if (isset($last_known_userinfo['code'])) {
      return $last_known_userinfo['code'];
    }
  }

  /**
   * Invalidates (removes) the password reset token from userinfo.
   */
  public static function invalidatePasswordResetToken() {
    $user_id = get_current_user_ID();
    $last_known_userinfo = get_user_meta($user_id, Plugin::USER_META_USERINFO, TRUE);
    unset($last_known_userinfo['code']);
    update_user_meta($user_id, Plugin::USER_META_USERINFO, $last_known_userinfo);
  }

  /**
   * Encrypts a given password using a (reversible) OpenSSL cipher.
   *
   * @param string $plain_value
   *   The plain value to encrypt.
   *
   * @return string
   *   The encrypted value.
   */
  public static function encrypt($plain_value) {
    $cipher = 'aes-256-cbc';
    $encryption_key = base64_decode(SSOFACT_ENCRYPTION_KEY);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    $encrypted = openssl_encrypt($plain_value, $cipher, $encryption_key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
  }

  /**
   * Decrypts a given encrypted password using a (reversible) OpenSSL cipher.
   *
   * @param string $encrypted_iv_encoded
   *   The encrypted value with iv, base64-encoded, to decrypt.
   *
   * @return string
   *   The decrypted value.
   */
  public static function decrypt($encrypted_iv_encoded) {
    list($encrypted_value, $iv) = explode('::', base64_decode($encrypted_iv_encoded));
    $cipher = 'aes-256-cbc';
    $encryption_key = base64_decode(SSOFACT_ENCRYPTION_KEY);
    $plain_value = openssl_decrypt($encrypted_value, $cipher, $encryption_key, 0, $iv);
    return $plain_value;
  }

  /**
   * Returns whether the current user is confirming the account after testing a premium article.
   */
  public static function isArticleTestConfirmationPage() {
    $user_id = get_current_user_ID();
    $userinfo = get_user_meta($user_id, Plugin::USER_META_USERINFO, TRUE);
    return $userinfo && empty($userinfo['alfa_purchases']) && !empty($userinfo['article_test']) && !empty($userinfo['code']);
  }

  /**
   * Loads front-end assets.
   */
  public static function wp_enqueue_scripts() {
    wp_enqueue_style('ssofact/woocommerce', Plugin::getBaseUrl() . '/assets/styles/woocommerce.css');
    wp_enqueue_script('ssofact/woocommerce', Plugin::getBaseUrl() . '/assets/scripts/woocommerce.js', ['jquery']);
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
