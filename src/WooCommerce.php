<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\WooCommerce.
 */

namespace Netzstrategen\Ssofact;

class WooCommerce {

  /**
   * Whether the checkout was initiated by an anonymous user.
   *
   * @var bool
   */
  private static $isAnonymousCheckout;

  /**
   * @implements woocommerce_email_actions
   */
  public static function woocommerce_email_actions($actions) {
    $actions[] = 'woocommerce_customer_save_address';
    return $actions;
  }

  /**
   * @implements woocommerce_email_classes
   */
  public static function woocommerce_email_classes($classes) {
    $classes['WooCommerceEmailAddressChanged'] = new WooCommerceEmailAddressChanged();
    return $classes;
  }

  /**
   * @implements woocommerce_email
   */
  public static function woocommerce_email($instance) {
    remove_action('woocommerce_low_stock_notification', [$instance, 'low_stock']);
    remove_action('woocommerce_no_stock_notification', [$instance, 'no_stock']);
    remove_action('woocommerce_product_on_backorder_notification', [$instance, 'backorder']);
    remove_action('woocommerce_created_customer_notification', [$instance, 'customer_new_account'], 10, 3);
  }

  /**
   * @implements woocommerce_created_customer
   */
  public static function woocommerce_created_customer($instance) {
    add_filter('send_auth_cookies', '__return_false');
  }

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
    $action = 'https://' . SSOFACT_SERVER_DOMAIN . '/?' . http_build_query([
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
   * Defines default address fields for checkout and user account forms.
   *
   * @implements woocommerce_default_address_fields
   */
  public static function woocommerce_default_address_fields($fields) {
    $fields['subscriber_id'] = [
      'label' => __('Existing subscription number', Plugin::L10N),
      'required' => FALSE,
      'priority' => 10,
    ];
    if (isset($fields['email'])) {
      $fields['email']['priority'] = 15;
    }
    $fields['salutation'] = [
      'type' => 'select',
      'label' => __('Salutation', Plugin::L10N),
      'options' => [
        '' => '',
        'Firma' => __('Company', 'woocommerce'),
        'Herr' => __('Mr.', Plugin::L10N),
        'Frau' => __('Ms.', Plugin::L10N),
      ],
      'required' => TRUE,
      'priority' => 20,
    ];
    // alfa VM does not have a dedicated address field for a company name; if
    // "Firma" is selected as salutation then the first name will be ignored and
    // the last name should contain the name of a contact person in the company.
    $fields['company']['required'] = TRUE;
    $fields['company']['priority'] = 30;
    $fields['company_contact'] = $fields['last_name'];
    $fields['company_contact']['label'] = __('Contact person', Plugin::L10N);
    $fields['company_contact']['priority'] = 35;
    $fields['first_name']['priority'] = 40;
    $fields['last_name']['priority'] = 50;
    $fields['postcode']['priority'] = 60;
    $fields['city']['priority'] = 70;
    // @todo What overrides this to "Adresszeile 1" in administrative user profile?
    $fields['address_1']['label'] = __('Street address', 'woocommerce');
    $fields['address_1']['priority'] = 80;
    $fields['house_number'] = [
      'type' => 'text',
      'label' => __('House num.', Plugin::L10N),
      'required' => TRUE,
      'priority' => 85,
    ];
    unset($fields['address_1']['placeholder']);
    unset($fields['address_2']);
    unset($fields['state']);
    $fields['country']['priority'] = 90;

    $fields['phone_prefix'] = [
      'type' => 'text',
      'label' => __('Phone prefix', Plugin::L10N),
      'required' => TRUE,
      'priority' => 100,
    ];
    if (isset($fields['phone'])) {
      $fields['phone']['priority'] = 105;
    }

    return $fields;
  }

  /**
   * Mirrors address field customizations to administrative user profile fields.
   *
   * @implements woocommerce_customer_meta_fields
   */
  public static function woocommerce_customer_meta_fields(array $fields) {
    // Fields in the admin user profile are seemingly output in the order they
    // are defined, so we have to recreate the fields in the desired order.
    // @todo Add optional parameter $prefix to
    //   WooCommerce::woocommerce_default_address_fields() to avoid at least
    //   this prefixing/unprefixing.
    foreach ($fields['billing']['fields'] as $key => $field) {
      unset($fields['billing']['fields'][$key]);
      $fields['billing']['fields'][str_replace('billing_', '', $key)] = $field;
    }

    $fields['billing']['fields'] = WooCommerce::woocommerce_default_address_fields($fields['billing']['fields']);
    $fields['billing']['fields'] = WooCommerce::sortFieldsByPriority($fields['billing']['fields']);

    foreach ($fields['billing']['fields'] as $key => $field) {
      unset($fields['billing']['fields'][$key]);
      $field += [
        'description' => '',
      ];
      $fields['billing']['fields']['billing_' . $key] = $field;
    }
    return $fields;
  }

  /**
   * Sorts woocommerce default address fields array by priority.
   */
  public static function sortFieldsByPriority($fields) {
    $fields = static::sortFieldsByKey($fields, 'priority');
    return $fields;
  }

  /**
   * Sorts an associative array by a given key.
   */
  public static function sortFieldsByKey($array, $key) {
    uasort($array, function ($a, $b) use ($key) {
      if (!isset($a[$key])) {
        $a[$key] = 0;
      }
      if (!isset($b[$key])) {
        $b[$key] = 0;
      }
      if ($a[$key] === $b[$key]) {
        return 0;
      }
      return ($a[$key] < $b[$key]) ? -1 : 1;
    });
    return $array;
  }

  /**
   * Removes unneeded fields from checkout shipping form.
   *
   * @implements woocommerce_checkout_fields
   */
  public static function woocommerce_checkout_fields($fields) {
    $fields['billing'] = WooCommerce::woocommerce_billing_fields($fields['billing']);
    $fields['shipping'] = WooCommerce::woocommerce_shipping_fields($fields['shipping']);

    // If the user only has a shipping address but no billing address (after
    // associating the existing subscription with the account but without a
    // purchase yet), use the shipping address as billing address by default.
    if (($user_id = get_current_user_ID()) && '' === get_user_meta($user_id, 'billing_address_1', TRUE) && '' !== get_user_meta($user_id, 'shipping_address_1', TRUE)) {
      foreach ($fields['shipping'] as $shipping_name => $field) {
        $billing_name = strtr($shipping_name, ['shipping_' => 'billing_']);
        if (isset($fields['billing'][$billing_name]) && '' !== $value = get_user_meta($user_id, $shipping_name, TRUE)) {
          $fields['billing'][$billing_name]['default'] = $value;
        }
      }
    }

    // Remove username and password fields from checkout form (email is username).
    unset($fields['account']['account_username']);
    unset($fields['account']['account_password']);
    return $fields;
  }

  /**
   * @implements woocommerce_billing_fields
   */
  public static function woocommerce_billing_fields($fields) {
    if (is_user_logged_in()) {
      // Changing the email address is a special process requiring to confirm
      // the new address, which should not be supported during checkout.
      unset($fields['billing_email']);

      // An existing subscriber ID cannot be changed.
      if (get_user_meta(get_current_user_ID(), 'billing_subscriber_id', TRUE)) {
        $fields['billing_subscriber_id']['required'] = TRUE;
        $fields['billing_subscriber_id']['custom_attributes']['readonly'] = 'readonly';
      }
    }

    // Require a company name if salutation has been set to company.
    $fields = WooCommerce::adjustCompanyFields($fields, 'billing');
    return $fields;
  }

  /**
   * @implements woocommerce_shipping_fields
   */
  public static function woocommerce_shipping_fields($fields) {
    unset($fields['shipping_subscriber_id']);
    unset($fields['shipping_phone_prefix']);

    // Require a company name if salutation has been set to company.
    $fields = WooCommerce::adjustCompanyFields($fields, 'shipping');
    return $fields;
  }

  public static function adjustCompanyFields(array $fields, $address_type) {
    if (!empty($_POST[$address_type . '_salutation']) && $_POST[$address_type . '_salutation'] === 'Firma') {
      $fields[$address_type . '_company']['required'] = TRUE;
      $fields[$address_type . '_company_contact']['required'] = TRUE;
      $fields[$address_type . '_first_name']['required'] = FALSE;
      $fields[$address_type . '_last_name']['required'] = FALSE;
    }
    else {
      $fields[$address_type . '_company']['required'] = FALSE;
      $fields[$address_type . '_company_contact']['required'] = FALSE;
      $fields[$address_type . '_first_name']['required'] = TRUE;
      $fields[$address_type . '_last_name']['required'] = TRUE;
    }
    return $fields;
  }

  /**
   * Appends the house number to the billing/shipping address in thankyou page.
   *
   * @implements woocommerce_get_order_address
   */
  public static function woocommerce_get_order_address($data, $type, $order) {
    $data['address_1'] .= ' ' . get_post_meta($order->get_id(), $type . '_house_number', TRUE);
    return $data;
  }

  /**
   * @implements woocommerce_localisation_address_formats
   */
  public static function woocommerce_localisation_address_formats($formats) {
    foreach ($formats as $country => $value) {
      $formats[$country] = strtr($formats[$country], [
        '{name}' => '{salutation}{name}',
        '{address_1}' => '{address_1}{house_number}',
        '{phone}' => '{phone_prefix}{phone}',
      ]);
    }
    return $formats;
  }

  /**
   * @implements woocommerce_formatted_address_replacements
   */
  public static function woocommerce_formatted_address_replacements($replacements, $fields) {
    $replacements['{salutation}'] = !empty($fields['salutation']) ? $fields['salutation'] . ' ' : '';
    $replacements['{house_number}'] = !empty($fields['house_number']) ? ' ' . $fields['house_number'] : '';
    $replacements['{phone_prefix}'] = !empty($fields['phone_prefix']) ? $fields['phone_prefix'] . '-' : '';
    return $replacements;
  }

  /**
   * Validates checkout fields against SSO.
   *
   * @implements woocommerce_checkout_process
   */
  public static function woocommerce_checkout_process() {
    // Verifies that the given email address is NOT registered yet.
    // Note: The API endpoint checks the opposite; a positive result is an error.
    // @todo Handle email change for logged-in users.
    if (!empty($_POST['billing_email']) && !is_user_logged_in()) {
      $response = Server::isEmailRegistered($_POST['billing_email']);
      // Error 607: "Given email is unknown" is the only allowed positive case.
      if (!isset($response['statuscode']) || $response['statuscode'] !== 607) {
        $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
        wc_add_notice($message, 'error');
        Server::addDebugMessage();
      }
    }
    // Check whether the given subscriber ID matches the registered address.
    if (!empty($_POST['billing_subscriber_id'])) {
      $response = Server::checkSubscriberId(
        $_POST['billing_subscriber_id'],
        $_POST['billing_first_name'] ?? '',
        $_POST['billing_last_name'] ?? $_POST['billing_company_contact'] ?? '',
        $_POST['billing_postcode'] ?? ''
      );
      if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
        $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
        wc_add_notice($message, 'error');
        Server::addDebugMessage();
      }
    }

    // WC_Checkout::process_customer() calls wp_set_current_user() after creating
    // the user account, causing the current user to be the customer account,
    // even though the user will not be authenticated (in our case). Therefore,
    // we need a flag that informs us about the original user authentication.
    static::$isAnonymousCheckout = !get_current_user_ID();
  }

  /**
   * Submits purchase to SSO.
   *
   * @implements woocommerce_checkout_order_processed
   */
  public static function woocommerce_checkout_order_processed($order_id, $posted_data, $order) {
    global $woocommerce;

    // Do not attempt to register purchase in case of a validation error.
    if (wc_notice_count('error') || empty($_POST['woocommerce_checkout_place_order']) || empty($_POST['terms'])) {
      return;
    }

    $cart = $woocommerce->cart->get_cart();
    foreach ($cart as $item) {
      $sku = $item['data']->get_sku();
    }
    if (empty($sku)) {
      throw new \LogicException("Unable to process order: Missing SKU (accessType) in selected product.");
    }
    $address_type = !empty($_POST['ship_to_different_address']) ? 'shipping' : 'billing';
    $purchase = Plugin::buildPurchaseInfo($sku, $address_type, static::$isAnonymousCheckout ? 0 : $order->get_customer_id());

    if (!static::$isAnonymousCheckout) {
      // Changing the email address is a special process requiring to confirm
      // the new address, which should not be supported during checkout.
      unset($purchase['email']);
      $response = Server::registerPurchase($purchase);
    }
    else {
      $purchase['confirmationUrl'] = wc_customer_edit_account_url();
      $response = Server::registerUserAndPurchase($purchase);
    }
    if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
      $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
      wc_add_notice($message, 'error');
      Server::addDebugMessage();
    }
    else {
      if (!empty($response['userId'])) {
        update_user_meta($order->get_customer_id(), 'openid-connect-generic-subject-identity', $response['userId']);
      }
      if (!empty($response['aboNo'])) {
        update_user_meta($order->get_customer_id(), 'billing_subscriber_id', $response['aboNo']);
      }
      Server::addDebugMessage();
    }
  }

  /**
   * @implements woocommerce_after_save_address_validation
   */
  public static function woocommerce_after_save_address_validation($user_id, $address_type, $address) {
    if (wc_notice_count('error')) {
      return;
    }
    $userinfo = Plugin::buildUserInfo($address_type, $user_id);

    $userinfo = array_diff_key($userinfo, [
      // Properties that cannot be changed by the client.
      'moddate' => 0,
      'lastchgdate' => 0,
      'profile_update_date' => 0,
      'last_login' => 0,
      'confirmed' => 0,
      'deactivated' => 0,
      'fcms_id' => 0,
      'facebook_id' => 0,
      // Properties that need special treatment and procedures.
      'email' => 0,
      'subscriber_id' => 0,
       // @todo Remove when gone.
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

    // @todo UX: Save last_edited timestamp and stop updating the locally stored
    //   user profile with UserInfo from SSO unless its last_updated timestamp
    //   is newer.
    // @todo Send different "action" depending on the action performed; i.e.,
    //   'initialPassword', 'forgotPassword', 'changeEmail', 'changePassword'.

    $response = Server::updateUser($userinfo);
    if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
      wc_add_notice(isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.'), 'error');
      Server::addDebugMessage();
    }
    Server::addDebugMessage();
  }

  /**
   * @implements woocommerce_save_account_details_errors
   */
  public static function woocommerce_save_account_details_errors(\WP_Error $errors, $user) {
    $current_user = wp_get_current_user();
    if (!empty($_POST['account_email']) && $_POST['account_email'] !== $current_user->user_email && !Plugin::getPasswordResetToken()) {
      if (empty($_POST['password_current'])) {
        wc_add_notice(__('Please enter your current password.', 'woocommerce'), 'error');
      }
      else {
        // Email and password cannot be changed at once currently.
        if (empty($_POST['password_1'])) {
          $all_notices = WC()->session->get('wc_notices', []);
          $index = array_search(__('Please fill out all password fields.', 'woocommerce'), $all_notices['error']);
          unset($all_notices['error'][$index]);
          WC()->session->set('wc_notices', $all_notices);
        }

        if (!wp_check_password($_POST['password_current'], $current_user->user_pass, $current_user->ID)) {
          wc_add_notice(__('Your current password is incorrect.', 'woocommerce'), 'error');
        }
        else {
          $response = Server::isEmailRegistered($_POST['account_email']);
          // Error 607: "Given email is unknown" is the only allowed positive case.
          if (!isset($response['statuscode']) || $response['statuscode'] !== 607) {
            $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
            wc_add_notice($message, 'error');
            Server::addDebugMessage();
          }
        }
      }
    }
    // Re-inject values for removed fields as they will be emptied otherwise.
    // @see WC_Form_Handler::save_account_details()
    $user->first_name = $current_user->first_name;
    $user->last_name = $current_user->last_name;
    $user->display_name = $current_user->display_name;
  }

  /**
   * @implements woocommerce_save_account_details
   */
  public static function woocommerce_save_account_details($user_id) {
    $userinfo = Plugin::buildUserInfo('account', $user_id);

    $current_email = $userinfo['email'];
    unset($userinfo['email']);

    if ($token = Plugin::getPasswordResetToken()) {
      $userinfo['pass'] = Plugin::encrypt($_POST['password_1']);
      $userinfo['code'] = $token;
      $userinfo['action'] = 'forgotPassword';
    }
    elseif (!empty($_POST['account_email']) && $_POST['account_email'] !== $current_email) {
      $userinfo['temp_email'] = $_POST['account_email'];
      $userinfo['pass_verify'] = Plugin::encrypt($_POST['password_current']);
      $userinfo['action'] = 'changeEmail';
    }
    if (!$token && !empty($_POST['password_1']) && !empty($_POST['password_current']) && $_POST['password_1'] !== $_POST['password_current']) {
      $first_userinfo = $userinfo;

      $userinfo['pass'] = Plugin::encrypt($_POST['password_1']);
      $userinfo['pass_verify'] = Plugin::encrypt($_POST['password_current']);
      $userinfo['action'] = 'changePassword';

      // SSO server only supports one action at a time, so if the user changed
      // the email and password at once, a second update needs to be performed.
      if (!empty($first_userinfo['action'])) {
        $second_userinfo = array_intersect_key($userinfo, ['id' => 1, 'pass' => 1, 'pass_verify' => 1, 'action' => 1]);
        $userinfo = $first_userinfo;
      }
    }

    $response = Server::updateUser($userinfo);
    if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
      wc_add_notice(isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.'), 'error');
      Server::addDebugMessage();
    }
    else {
      // Perform the password change in a second request if email and password
      // were changed at once.
      if (!empty($second_userinfo)) {
        $response = Server::updateUser($second_userinfo);
        if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
          wc_add_notice(isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.'), 'error');
          Server::addDebugMessage();
        }
      }
      // Invalidate the one-time token immediately on successful update.
      Plugin::invalidatePasswordResetToken();
    }
    Server::addDebugMessage();
  }

  /**
   * Removes core profile fields and adds opt-ins on account edit form.
   *
   * @implements woocommerce_edit_account_form
   */
  public static function woocommerce_edit_account_form() {
    $form = ob_get_clean();
    $form = preg_replace('@^\s*<p.+?(?:account_first_name|account_last_name|account_display_name).+?</p>@sm', '', $form);
    if (Plugin::getPasswordResetToken()) {
      $form = preg_replace('@^\s*<p.+?(?:password_current).+?</p>@sm', '', $form);
    }
    echo $form;

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
   * Disables validation for core profile fields in account edit form.
   *
   * @implements woocommerce_save_account_details_required_fields
   */
  public static function woocommerce_save_account_details_required_fields(array $fields) {
    unset($fields['account_first_name'], $fields['account_last_name']);
    unset($fields['account_display_name']);
    if (Plugin::getPasswordResetToken()) {
      unset($fields['account_email']);
      unset($fields['password_current']);
    }
    return $fields;
  }

  /**
   * @implements woocommerce_before_template_part
   */
  public static function woocommerce_before_template_part($template_name) {
    if ($template_name === 'myaccount/form-lost-password.php' || $template_name === 'myaccount/my-subscriptions.php') {
      ob_start();
    }
  }

  /**
   * Posts forgot password (request) form to SSO with additional redirect URL.
   *
   * @implements woocommerce_lostpassword_form
   */
  public static function woocommerce_lostpassword_form() {
    $output = ob_get_clean();
    $authorize_uri = Plugin::getAuthorizeUrl();
    $action = 'https://' . SSOFACT_SERVER_DOMAIN . '/passwort-vergessen.html';
    $output = strtr($output, [
      '<form method="post"' => '<form method="post" action="' . $action . '"',
      'name="user_login"' => 'name="email"',
    ]);
    echo $output;

    $redirect_url = site_url('shop/user/account');
    ?>
  <input type="hidden" name="redirect_url" value="<?= $redirect_url ?>">
    <?php
  }

  /**
   * @implements woocommerce_after_template_part
   */
  public static function woocommerce_after_template_part($template_name) {
    if ($template_name === 'myaccount/my-subscriptions.php') {
      $output = ob_get_clean();
      echo Alfa::mapPurchases(Alfa::getPurchases());
      echo $output;
    }
  }

  /**
   * @implements wcs_user_has_subscription
   */
  public static function wcs_user_has_subscription($has_subscription, $user_id, $product_id, $status) {
    if (empty($product_id)) {
      $has_subscription = (bool) get_user_meta($user_id, 'paying_customer', TRUE);
    }
    return $has_subscription;
  }

  /**
   * @implements woocommerce_account_view-subscription_endpoint
   */
  public static function viewSubscription() {
    Alfa::mapPurchases(Alfa::getPurchases());
  }

}
