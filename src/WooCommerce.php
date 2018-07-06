<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\WooCommerce.
 */

namespace Netzstrategen\Ssofact;

class WooCommerce {

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
        'Herr' => __('Mr.', Plugin::L10N),
        'Frau' => __('Ms.', Plugin::L10N),
      ],
      'required' => TRUE,
      'priority' => 20,
    ];
    $fields['first_name']['priority'] = 30;
    $fields['last_name']['priority'] = 40;
    $fields['company']['priority'] = 50;
    $fields['postcode']['priority'] = 60;
    $fields['city']['priority'] = 70;
    $fields['state']['priority'] = 75;
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
    return $fields;
  }

  /**
   * @implements woocommerce_billing_fields
   */
  public static function woocommerce_billing_fields($fields) {
    // Changing the email address is a special process requiring to confirm
    // the new address, which should not be supported during checkout.
    if (is_user_logged_in()) {
      unset($fields['billing_email']);
    }
    return $fields;
  }

  /**
   * @implements woocommerce_shipping_fields
   */
  public static function woocommerce_shipping_fields($fields) {
    unset($fields['shipping_subscriber_id']);
    unset($fields['shipping_phone_prefix']);
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
    global $woocommerce;

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
        $_POST['billing_first_name'],
        $_POST['billing_last_name'],
        $_POST['billing_postcode']
      );
      if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
        $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
        wc_add_notice($message, 'error');
        Server::addDebugMessage();
      }
    }

    // Do not attempt to register purchase in case of a validation error.
    if (wc_notice_count('error') || empty($_POST['woocommerce_checkout_place_order']) || empty($_POST['terms'])) {
      return;
    }

    // @todo Move from checkout validation into submission?
    //   @see woocommerce_checkout_order_processed
    $cart = $woocommerce->cart->get_cart();
    foreach ($cart as $item) {
      $sku = $item['data']->get_sku();
    }
    $purchase = Plugin::buildPurchaseInfo($sku);

    // If a registered user has a subscriber ID already, then alfa GP/VM will
    // reject any kind of change to the address. The submitted address will only
    // be contained in the order confirmation email and manually processed by
    // the customer service team. Even if the user only specified the ID in the
    // checkout form, it has already been validated to be correct by now.
    if (!empty($_POST['billing_subscriber_id']) || (is_user_logged_in() && get_user_meta(get_current_user_ID(), 'subscriber_id', TRUE))) {
      $purchase = array_diff_key($purchase, [
        'salutation' => 0,
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
    if (is_user_logged_in()) {
      // Changing the email address is a special process requiring to confirm
      // the new address, which should not be supported during checkout.
      unset($purchase['email']);
      $response = Server::registerPurchase($purchase);
    }
    else {
      $response = Server::registerUserAndPurchase($purchase);
    }
    if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
      $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
      wc_add_notice($message, 'error');
      Server::addDebugMessage();
    }
    else {
      Server::addDebugMessage();
    }
  }

  /**
   * @implements woocommerce_after_save_address_validation
   */
  public static function woocommerce_after_save_address_validation($user_id, $address_type, $address) {
    $last_known_userinfo = get_user_meta($user_id, Plugin::USER_META_USERINFO, TRUE);

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
    if (isset($_POST[$address_type . '_phone'])) {
      $userinfo['phone_prefix'] = $_POST[$address_type . '_phone_prefix'];
      $userinfo['phone'] = $_POST[$address_type . '_phone'];
    }
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

    // @todo Address can no longer be updated via updateUser API if there is
    //   both (1) a subscriber ID and (2) a (street) address. Once both exist,
    //   the address is forwarded to alfa VM and set in stone, and may only be
    //   changed by the customer service team; an email needs to be sent instead.
    // @todo UX: Save last_edited timestamp and stop updating the locally stored
    //   user profile with UserInfo from SSO unless its last_updated timestamp
    //   is newer.
    // @todo Coordinate how to handle the shipping address or which one to send.
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
    if (!empty($_POST['account_email']) && $_POST['account_email'] !== $current_user->user_email) {
      $response = Server::isEmailRegistered($_POST['account_email']);
      // Error 607: "Given email is unknown" is the only allowed positive case.
      if (!isset($response['statuscode']) || $response['statuscode'] !== 607) {
        $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
        wc_add_notice($message, 'error');
        Server::addDebugMessage();
      }
    }
  }

  /**
   * @implements woocommerce_save_account_details
   */
  public static function woocommerce_save_account_details($user_id) {
    $userinfo = Plugin::buildUserInfo($user_id, 'account');
    $userinfo['email'] = $_POST['account_email'];

    $response = Server::updateUser($userinfo);
    if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
      wc_add_notice(isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.'), 'error');
      Server::addDebugMessage();
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
    return $fields;
  }

}
