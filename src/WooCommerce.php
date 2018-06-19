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
    if ($_POST['billing_email']) {
      $response = Server::isEmailRegistered($_POST['billing_email']);
      // 202 and 602: email already in use
      if ($response[0]['statuscode'] === 200 || $response[0]['statuscode'] === 602) {
        $message = $response[0]['userMessages'][0];
      }
    }
    // Checks if the subscription ID matches.
    if ($_POST['subscription_id']) {
      $response = Server::checkSubscriptionNumber(
        $_POST['subscription_id'],
        $_POST['billing_first_name'],
        $_POST['billing_last_name'],
        $_POST['billing_postcode']
      );
      // 614: abono combination not ok
      if ($response['statuscode'] === 614) {
        $message = $response['userMessages'][0];
      }
    }
    if ($message) {
      wp_send_json([
        'result' => 'failure',
        'messages' => wc_add_notice($message, 'error'),
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
    if (empty($userinfo['id'])) {
      throw new \LogicException('Unable to update account: Missing SSO ID in last known UserInfo.');
    }
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
    // @todo Coordinate list of country codes.
    // $userinfo['country'] = $_POST[$address_type . '_country']; // Deutschland vs. DE
    $phone = explode('-', $_POST[$address_type . '_phone'], 2);
    $userinfo['phone_prefix'] = $phone[0] ?? '';
    $userinfo['phone'] = $phone[1] ?? '';
    // @todo Web user account only supports a single phone number (due to data
    //   privacy and because landline numbers are a thing of the past). Migrate
    //   these to phone upon login/import.
    // unset($userinfo['mobile_prefix']);
    // unset($userinfo['mobile']);

    $userinfo = array_diff_key($userinfo, [
      // Properties that cannot be changed by the client.
      'moddate' => 0,
      'lastchgdate' => 0,
      'last_login' => 0,
      'confirmed' => 0,
      'deactivated' => 0,
      'fcms_id' => 0,
      'facebook_id' => 0,
      // Properties that need special treatment and procedures.
      'email' => 0,
      'customer_id' => 0,
      'subscribernr' => 0,
      // Properties that are edited elsewhere.
      'roles' => 0,
      'article_test' => 0,
    ]);

    // Opt-ins that cannot be changed by the client.
    $userinfo['optins'] = array_diff_key($userinfo['optins'], [
      'email_doi' => 0,
      'changemail' => 0,
    ]);

    // @todo Address can no longer be updated via updateUser API if there is
    //   both (1) a subscription ID and (2) a (street) address. Once both exist,
    //   the address is forwarded to alfa VM and set in stone, and may only be
    //   changed by the customer service team; an email needs to be sent instead.
    // @todo UX: Save last_edited timestamp and stop updating the locally stored
    //   user profile with UserInfo from SSO unless its last_updated timestamp
    //   is newer.
    // @todo Coordinate how to handle the shipping address or which one to send.
    // @todo Send different "action" depending on the action performed; i.e.,
    //   'initialPassword', 'forgotPassword', 'changeEmail', 'changePassword'.

    $response = Server::updateUser($userinfo);
    if ($response['statuscode'] !== 200) {
      if (WP_DEBUG) {
        echo "<pre>\n"; var_dump(json_encode($userinfo, JSON_PRETTY_PRINT), json_encode($response, JSON_PRETTY_PRINT)); echo "</pre>";
      }
      wc_add_notice(implode('<br>', $response['userMessages']), 'error');
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
}
