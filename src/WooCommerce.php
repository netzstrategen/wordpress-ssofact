<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\WooCommerce.
 */

namespace Netzstrategen\Ssofact;

class WooCommerce {

  /**
   * @implements woocommerce_before_customer_login_form
   */
  public static function woocommerce_before_customer_login_form() {
    ob_start();
  }

  /**
   * @implements woocommerce_after_customer_login_form
   */
  public static function woocommerce_after_customer_login_form() {
    $output = ob_get_clean();
    $authorize_uri = Plugin::getAuthorizeUrl();
    $server_domain = 'stage-login.stimme.de';
    $action = 'https://' . $server_domain . '/index.php?' . http_build_query([
      'next' => $authorize_uri,
    ]);
    $output = strtr($output, [
      'login" method="post">' => 'login" method="post" action="' . $action . '">',
      'name="login"' => 'name="submit"',
      'name="username"' => 'name="login"',
      'name="password"' => 'name="pass"',
      'checkbox inline">' => 'checkbox inline" hidden>',
      'name="rememberme" type="checkbox"' => 'name="permanent_login" type="hidden"',
      'value="forever"' => 'value="1"',
    ]);
    echo $output;
  }

  /**
   * Validates checkout fields against SSO.
   *
   * @implements woocommerce_checkout_process
   */
  public static function woocommerce_checkout_process() {
    // Checks if the email address is already registered.
    // @todo Handle email change for logged-in users.
    if (!empty($_POST['billing_email']) && !is_user_logged_in()) {
      $response = Server::isEmailRegistered($_POST['billing_email']);
      // @todo Remove error 607: "Given email is unknown" (false error)
      if ($response['statuscode'] !== 200 && $response['statuscode'] !== 607) {
        $message = implode('<br>', $response['userMessages']);
        static::addDebugMessage($_POST['billing_email'], $response);
      }
    }
    // Checks if the subscription ID matches.
    if (!empty($_POST['subscription_id'])) {
      $response = Server::checkSubscriptionNumber(
        $_POST['subscription_id'],
        $_POST['billing_first_name'],
        $_POST['billing_last_name'],
        $_POST['billing_postcode']
      );
      // 614: abono combination not ok
      if ($response['statuscode'] === 614) {
        $message = implode('<br>', $response['userMessages']);
      }
    }
    if (!empty($message)) {
      wp_send_json([
        'result' => 'failure',
        'messages' => wc_add_notice($message, 'error'),
        'reload' => TRUE,
      ]);
    }
    // @todo Move from checkout validation into submission?
    //   @see woocommerce_checkout_order_processed
    if (is_user_logged_in()) {
      $purchase = Plugin::buildPurchaseInfo();
      $response = Server::registerPurchase($purchase);
    }
    else {
      $purchase = Plugin::buildPurchaseInfo();
      $response = Server::registerUserAndPurchase($purchase);
    }
    if ($response['statuscode'] !== 200) {
      wp_send_json([
        'result' => 'failure',
        'messages' => wc_add_notice(implode('<br>', $response['userMessages']), 'error') . static::addDebugMessage($purchase, $response),
        'reload' => TRUE,
      ]);
    }
  }

  /**
   * @implements woocommerce_after_save_address_validation
   */
  public static function woocommerce_after_save_address_validation($user_id, $address_type, $address) {
    $last_known_userinfo = get_user_meta($user_id, 'ssofact_userinfo', TRUE);

    $userinfo = $last_known_userinfo;
    // $userinfo['id'] = (string) $userinfo['id'];
    $userinfo['email'] = $_POST[$address_type . '_email'];
    $userinfo['salutation'] = $_POST[$address_type . '_salutation'];
    // $userinfo['title'] = $_POST[$address_type . '_title'];
    $userinfo['firstname'] = $_POST[$address_type . '_first_name'];
    $userinfo['lastname'] = $_POST[$address_type . '_last_name'];
    // $userinfo['company'] = $_POST[$address_type . '_company'];
    $userinfo['street'] = $_POST[$address_type . '_address_1'];
    $userinfo['housenr'] = $_POST[$address_type . '_house_number'];
    $userinfo['zipcode'] = $_POST[$address_type . '_postcode'];
    $userinfo['city'] = $_POST[$address_type . '_city'];
    // $userinfo['country'] = $_POST[$address_type . '_country']; // Deutschland vs. DE
    $phone = explode('-', $_POST[$address_type . '_phone'], 2);
    $userinfo['phone_prefix'] = $phone[0] ?? '';
    $userinfo['phone'] = $phone[1] ?? '';
    // $userinfo['optins'] = $_POST[''];

    // @todo Which user profile data will be accepted by SSO in updateUser?
    // unset($userinfo['moddate']);
    // unset($userinfo['lastchgdate']);
    // unset($userinfo['last_login']);
    //
    // unset($userinfo['email']);
    // unset($userinfo['customer_id']);
    // unset($userinfo['subscribernr']);
    // unset($userinfo['fcms_id']);
    // unset($userinfo['facebook_id']);
    // unset($userinfo['confirmed']);
    // unset($userinfo['deactivated']);
    // unset($userinfo['roles']);
    // unset($userinfo['article_test']);
    //
    // unset($userinfo['title']);
    // unset($userinfo['mobile_prefix']);
    // unset($userinfo['mobile']);
    // unset($userinfo['phone_prefix']);
    // unset($userinfo['phone']);
    // $userinfo['country'] = 'DE';
    // unset($userinfo['country']);
    // unset($userinfo['optins']);

    // @todo Existing postal address data cannot be updated until v2; needs to
    //   be sent via email instead.
    // @todo Nice-to-have for UX: Save edited values in local user profile along
    //   with a last_edited timestamp and only replace the local values when
    //   last_updated timestamp from SSO is newer.
    // @todo Coordinate how to handle the shipping address or which one to send.
    // @todo Send different "action" depending on the action performed; i.e.,
    //   'initialPassword', 'forgotPassword', 'changeEmail', 'changePassword'.
    // $userinfo = array_intersect_key($userinfo, [
    //   'id' => 1,
    //   'optins' => 1,
    // ]);

    $response = Server::updateUser($userinfo);
    if ($response['statuscode'] !== 200) {
      wc_add_notice(implode('<br>', $response['userMessages']), 'error');
      static::addDebugMessage($userinfo, $response);
    }
  }

  /**
   * @implements woocommerce_save_account_details
   */
  public static function woocommerce_save_account_details($user_id) {
    $userinfo = [];
    $userinfo['id'] = $user_id;
    $userinfo['firstname'] = $_POST['account_first_name'];
    $userinfo['lastname'] = $_POST['account_last_name'];
    $userinfo['email'] = $_POST['account_email'];

    if ($_POST['password_1'] && $_POST['password_1'] === $_POST['password_2']) {
      $userinfo['password'] = $_POST['password_1'];
    }

    $userinfo['list_noch-fragen'] = $_POST['list_noch-fragen'] ?? 0;
    $userinfo['list_premium'] = $_POST['list_premium'] ?? 0;
    $userinfo['list_freizeit'] = $_POST['list_freizeit'] ?? 0;
    $userinfo['confirm_agb'] = $_POST['confirm_agb'] ?? 0;

    $response = Server::updateUser($userinfo);
    if ($response['statuscode'] !== 200) {
      wc_add_notice(implode('<br>', $response['userMessages']), 'error');
      static::addDebugMessage($userinfo, $response);
    }
  }

  /*
   * Displays opt-in checkboxes in user account edit form.
   *
   * @implements woocommerce_edit_account_form
   */
  public static function woocommerce_edit_account_form() {
    echo '<fieldset class="account-edit-optin-checks">';

    $opt_ins = [
      'list_noch-fragen' => [
        'label' => 'Newsletter Noch Fragen',
        'priority' => 100,
      ],
      'list_premium' => [
        'label' => 'Newsletter Premium',
        'priority' => 110,
      ],
      'list_freizeit' => [
        'label' => 'Newsletter Freizeit',
        'priority' => 120,
      ],
      'confirm_agb' => [
        'label' => 'AGB-Bestätigung',
        'priority' => 130,
      ],
    ];

    foreach ($opt_ins as $opt_in_id => $opt_in_args) {
      $args = [
        'type' => 'checkbox',
        'label' => $opt_in_args['label'],
        'required' => FALSE,
        'id' => $opt_in_id,
        'priority' => $opt_in_args['priority'],
      ];

      woocommerce_form_field($opt_in_id, $args);
    }
    echo '</fieldset>';
  }

  /**
   * Adds WooCommerce notice with debug information if WP_DEBUG is enabled.
   *
   * @param mixed $request_data
   * @param mixed $response_data
   */
  public static function addDebugMessage($request_data, $response_data) {
    if (WP_DEBUG) {
      return wc_add_notice("<pre>\n"
        . json_encode($request_data, JSON_PRETTY_PRINT) . "\n"
        . json_encode($response_data, JSON_PRETTY_PRINT)
        . "\n</pre>"
      , 'notice');
    }
  }

}
