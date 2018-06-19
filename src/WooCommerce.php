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
    if (!empty($_POST['billing_email']) && !is_user_logged_in()) {
      $response = Server::isEmailRegistered($_POST['billing_email']);
      // 202 and 602: email already in use
      if ($response[0]['statuscode'] === 200 || $response[0]['statuscode'] === 602) {
        $message = $response[0]['userMessages'][0];
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
        $message = $response['userMessages'][0];
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
      if ($response['statuscode'] !== 200) {
        if (WP_DEBUG) {
          echo "<pre>\n"; var_dump(json_encode($purchase, JSON_PRETTY_PRINT), json_encode($response, JSON_PRETTY_PRINT)); echo "</pre>";
        }
        wc_add_notice(implode('<br>', $response['userMessages']), 'error');
      }
    }
  }

  /**
   * @implements woocommerce_after_save_address_validation
   */
  public static function woocommerce_after_save_address_validation($user_id, $load_address, $address) {
    $last_known_userinfo = get_user_meta($user_id, 'ssofact_userinfo', TRUE);

    $userinfo = $last_known_userinfo;
    $userinfo['email'] = $_POST[$load_address . '_email'];
    // $userinfo['salutation'] = $_POST[$load_address . '_'];
    // $userinfo['title'] = $_POST[$load_address . '_'];
    $userinfo['firstname'] = $_POST[$load_address . '_first_name'];
    $userinfo['lastname'] = $_POST[$load_address . '_last_name'];
    // $userinfo['company'] = $_POST[$load_address . '_company'];
    $userinfo['street'] = $_POST[$load_address . '_address_1'];
    $userinfo['housenr'] = $_POST[$load_address . '_house_number'];
    $userinfo['zipcode'] = $_POST[$load_address . '_postcode'];
    $userinfo['city'] = $_POST[$load_address . '_city'];
    // $userinfo['country'] = $_POST[$load_address . '_country']; // Deutschland vs. DE
    $phone = explode('-', $_POST[$load_address . '_phone'], 2);
    $userinfo['phone_prefix'] = $phone[0] ?? '';
    $userinfo['phone'] = $phone[1] ?? '';
    // $userinfo['optins'] = $_POST[''];

    $response = Server::updateUser($userinfo);
    if ($response['statuscode'] !== 200) {
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
