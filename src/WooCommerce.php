<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\WooCommerce.
 */

namespace Netzstrategen\Ssofact;

class WooCommerce {

  const REMOVE_FIELD_CHECKOUT_SECTION_PREFIX = '###REMOVEPREFIX';

  const PAYMENT_INTERVALS = [
    'm' => 'monthly',
    'v' => 'quarterly',
    'h' => 'half-yearly',
    'j' => 'yearly',
  ];

  const OPTINS = [
    'list_redaktion-stimmede-editorial-weekly' => [
      'label' => 'Newsletter des Chefredakteurs',
      'priority' => 100,
    ],
    'list_redaktion-stimmede-editorial-premium-daily' => [
      'label' => 'Morgen-Briefing aus der Redaktion',
      'priority' => 110,
    ],
    /*
    'list_redaktion-stimmede-service-freizeit-custom' => [
      'label' => 'Freizeit-Tipps fürs Wochenende',
      'priority' => 120,
    ],
    */
    'acquisitionEmail' => [
      'label' => 'Ja, ich möchte auch zukünftig über aktuelle Angebote aus dem Verlags- und Dienstleistungsbereich der Mediengruppe Heilbronner Stimme per Telefon und/oder E-Mail informiert werden. Mein Einverständnis hierzu kann ich jederzeit widerrufen.',
      'priority' => 120,
    ],
  ];

  /**
   * Whether the checkout was initiated by an anonymous user.
   *
   * @var bool
   */
  private static $isAnonymousCheckout;

  /**
   * Whether the subscriber association user input has been processed (once).
   *
   * @var bool
   */
  private static $isSubscriberAssociationProcessed = FALSE;

  /**
   * Whether the checkout form is actually submitted.
   *
   * @var bool
   */
  private static $isFinalCheckoutSubmission;

  /**
   * Whether the checkout form is for the trial subscription with less required fields.
   *
   * @var bool
   */
  private static $isTrialSubscriptionCheckout;

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
   * Returns URLs for login form action, and forgot password and register links.
   */
  public static function getLoginFormUrls($target = NULL) {
    if (!isset($target)) {
      $target = $_REQUEST['redirect_to'] ?? '/user';
    }
    $next = site_url($target);
    $authorize_uri = Plugin::getAuthorizeUrl($target);
    $action = 'https://' . SSOFACT_SERVER_DOMAIN . '/?' . http_build_query([
      'next' => $authorize_uri,
    ]);
    $href_forgot_password = 'https://' . SSOFACT_SERVER_DOMAIN . '/?' . http_build_query([
      'pageid' => 53,
      'next' => site_url('/shop/user/account'),
    ]);
    $href_register = 'https://' . SSOFACT_SERVER_DOMAIN . '/registrieren.html?' . http_build_query([
      'next' => $next,
    ]);
    return [
      'action' => $action,
      'href_forgot_password' => $href_forgot_password,
      'href_register' => $href_register,
    ];
  }

  /**
   * @implements woocommerce_after_customer_login_form
   */
  public static function woocommerce_after_customer_login_form() {
    // Ignore output, the whole form is replaced with the SSO markup.
    $output = ob_get_clean();

    extract(WooCommerce::getLoginFormUrls());
    ?>
<link rel="stylesheet" href="https://<?= SSOFACT_SERVER_DOMAIN ?>/pu_stimme/styles_frametemplate/01_styles.css" media="all" />
<script>
var nfycDisableRedirect = true;
var nfyFacebookAppId = '637920073225349';
</script>
<script src="https://<?= SSOFACT_SERVER_DOMAIN ?>/cms_media/minify/2/javascript/javascript_4.js"></script>

<div class=nfy-sso-hs>
  <div class="nfy-box nfy-website-user nfy-box-login">
    <h1 class="nfy-element-header">Anmelden</h1>
    <div class="nfy-box-content">
      <form action="<?= $action ?>" method="post" name="loginForm" id="loginForm" class="nfy-form nfy-flex-form">
        <div class="form-item field">
          <input maxlength="255" placeholder="E-Mail-Adresse eingeben" name="login" type="text" />
        </div>
        <div class="form-item field">
          <input maxlength="255" placeholder="Passwort eingeben" name="pass" type="password" />
        </div>
        <div class="form-item field nfy-checkbox">
          <label class="choice-input checkbox">
            <input type="hidden" name="permanent_login" value="" />
            <input id="nfy-qform-checkbox" name="permanent_login" type="checkbox" value="1" />
              <span class="choice-input__indicator checkbox__indicator"></span>
              <span class="choice-input__label">Angemeldet bleiben</span>
          </label>
        </div>
        <a class="button--link button" href="<?= $href_forgot_password ?>">Passwort vergessen?</a>
        <input class="button--primary button" value="Anmelden" type="submit" />
        <!-- <input name="redirect_url" type="hidden" value="" /> -->
      </form>
    </div>
  </div>
  <div class="nfy-social-login-text">Alternativ mit Facebook anmelden</div>
  <div class="fb-login-button" data-scope="public_profile,email" data-width="500"
  onlogin="nfyFacebookStatusCallback()" data-max-rows="1" data-size="large"
  data-button-type="login_with" data-show-faces="false" data-auto-logout-link="false"
  data-use-continue-as="false"></div>
  <div class="nfy-box-info nfy-register-link-info">
    <div class=nfy-box>Sie sind noch nicht registriert?
      <a class=nfy-link href="<?= $href_register ?>">Hier registrieren</a>
    </div>
  </div>
</div>
    <?php
  }

  /**
   * @implements woocommerce_login_form_end
   */
  public static function woocommerce_login_form_end() {
    $output = ob_get_clean();
    extract(WooCommerce::getLoginFormUrls('/shop/checkout'));

    $output = strtr($output, [
      'login" method="post"' => 'login" method="post" action="' . $action . '"',
      'name="login"' => 'name="submit" data-action="' . $action . '"',
      'name="username"' => 'name="login"',
      'name="password"' => 'name="pass"',
      'checkbox inline">' => 'checkbox inline" hidden>',
      'name="rememberme" type="checkbox"' => 'name="permanent_login" type="hidden"',
      'value="forever"' => 'value="1"',
      site_url('/shop/user/lost-password') => $href_forgot_password,
    ]);
    echo $output;
    // Add links to switch between the login and register forms.
    if (is_checkout()) {
      ?>
<div class="login__footer">
  <p class="account--register">Sie haben noch kein Kundenkonto? Hier gehts zur <a id="switchToRegister" href="#">Registrierung</a>.</p>
  <p class="account--login" style="display: none;">Sie haben schon ein Konto? Hier gehts zum <a id="switchToLogin" href="#">Login</a>.</p>
</div>
      <?php
    }
  }

  /**
   * @implements woocommerce_checkout_billing
   */
  public static function woocommerce_checkout_billing() {
    if (is_user_logged_in()) {
      return;
    }
    woocommerce_login_form(['redirect' => wc_get_page_permalink('checkout')]);
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
      'class' => ['field--salutation'],
    ];
    // alfa VM does not have a dedicated address field for a company name; if
    // "Firma" is selected as salutation then the first name will be ignored and
    // the last name should contain the name of a contact person in the company.
    $fields['company']['required'] = TRUE;
    $fields['company']['priority'] = 30;
    $fields['company']['class'] = ['field--company'];
    $fields['company_contact'] = $fields['last_name'];
    $fields['company_contact']['label'] = __('Contact person', Plugin::L10N);
    $fields['company_contact']['priority'] = 35;
    $fields['company_contact']['class'] = ['field--company_contact'];
    $fields['first_name']['priority'] = 40;
    $fields['first_name']['class'] = ['field--first_name'];
    $fields['last_name']['priority'] = 50;
    $fields['last_name']['class'] = ['field--last_name'];
    $fields['postcode']['priority'] = 60;
    $fields['postcode']['class'] = ['field--postcode'];
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
      $fields['phone']['priority'] = 110;
    }

    // To increase conversions for the free trial subscription (SKU 'stite'),
    // only first_name and last_name (and opt-ins) should be required.
    // Must be performed in this hook, as documented by WooCommerce Core.
    // @see WC_Countries::get_address_fields()
    if (is_checkout()) {
      $sku = NULL;
      foreach (WC()->cart->get_cart() as $item) {
        $sku = $item['data']->get_sku();
      }
      if ($sku === 'stite') {
        static::$isTrialSubscriptionCheckout = TRUE;
        foreach (['address_1', 'house_number', 'postcode', 'city', 'country', 'phone_prefix', 'phone'] as $key) {
          if (isset($fields[$key])) {
            $fields[$key]['required'] = FALSE;
          }
        }
      }
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

    if (!empty(static::$isTrialSubscriptionCheckout)) {
      // Phone field is added after default address fields.
      // @see WC_Countries::get_address_fields()
      $fields['billing']['billing_phone']['required'] = FALSE;
    }

    // Allow the customer to choose a payment interval if the subscription will
    // run infinitely (and thus not be paid once upfront).
    if (WC()->cart->get_total('edit') > 0) {
      foreach (WC()->cart->cart_contents as $cart_item) {
        if (\WC_Subscriptions_Product::is_subscription($cart_item['data'])) {
          $length = \WC_Subscriptions_Product::get_length($cart_item['data']);
        }
      }
      if (isset($length) && $length === 0) {
        $fields['billing']['payment_interval'] = [
          'type' => 'radio',
          'label' => __('Payment interval', Plugin::L10N),
          'options' => [
            'm' => __('monthly', Plugin::L10N),
            'v' => __('quarterly', Plugin::L10N),
            'h' => __('half-yearly', Plugin::L10N),
            'j' => __('yearly', Plugin::L10N),
          ],
          'required' => TRUE,
          'default' => 'm',
          'priority' => 200,
        ];
      }
    }

    if (!static::$isFinalCheckoutSubmission && !empty($_POST['step'])) {
      add_filter('woocommerce_add_success', __CLASS__ . '::woocommerce_add_success');
      switch ($_POST['step']) {
        case 'login':
        case 'account':
          foreach ($fields['billing'] as $key => $field) {
            if ($key !== 'billing_email') {
              $fields['billing'][$key]['required'] = FALSE;
            }
          }

        case 'address':
          $fields['billing']['payment_interval']['required'] = FALSE;

          // Disable validation of direct debit fields of woocommerce-german-market.
          remove_filter('gm_checkout_validation_first_checkout', ['WGM_Gateway_Sepa_Direct_Debit', 'validate_required_fields']);
          break;
      }
    }

    return $fields;
  }

  /**
   * @implements woocommerce_add_success
   */
  public static function woocommerce_add_success($notice_message) {
    // When submitting the checkout form with 'woocommerce_checkout_update_totals'
    // (which we are doing between checkout steps) then WooCommerce believes that
    // the checkout form was submitted without AJAX/JS in order to update the
    // shipping costs; remove this unnecessary/confusing message.
    // @see WC_Shortcode_Checkout::checkout()
    if ($notice_message === __('The order totals have been updated. Please confirm your order by pressing the "Place order" button at the bottom of the page.', 'woocommerce')) {
      $notice_message = '';
    }
    return $notice_message;
  }

  /**
   * @implements woocommerce_checkout_get_value
   *
   * @todo WooCommerce does not store values of custom address fields nor custom
   *   meta data in the customer user session since ~2.7. Fields starting with
   *   'billing_' or 'shipping_' are still supposed to work, but only seem to be
   *   taken over into the order in a direct POST, but not when the checkout
   *   reloads.
   * @see https://github.com/woocommerce/woocommerce/issues/6226
   * @see https://github.com/woocommerce/woocommerce/issues/12634
   * @see https://docs.woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/
   * @see https://stackoverflow.com/questions/45602936/woocommerce-set-checkout-field-values
   */
  public static function woocommerce_checkout_get_value($value, $field) {
    if ($value === NULL && ($session_value = WC()->session->get($field))) {
      $value = $session_value;
    }
    return $value;
  }

  /**
   * @implements woocommerce_ship_to_different_address_checked
   *
   * @thanks https://stackoverflow.com/questions/37459797/woocommerce-how-to-retain-checkout-info-when-client-leaves-then-comes-back
   */
  public static function woocommerce_ship_to_different_address_checked($value) {
    if ($value === FALSE && ($session_value = WC()->session->get('ship_to_different_address'))) {
      $value = $session_value;
    }
    return $value;
  }

  /**
   * @implements gm_sepa_fields_in_checkout
   */
  public static function gm_sepa_fields_in_checkout($fields) {
    foreach ($fields as $key => $field) {
      // Relies on WGM's own "first checkout" session data.
      if ($field['value'] === '' && ($session_value = WC()->session->get('first_checkout_post_arraygerman-market-sepa-' . $key))) {
        $fields[$key]['value'] = $session_value;
      }
    }
    return $fields;
  }

  /**
   * Returns whether the current page is the second/final checkout confirmation page.
   */
  public static function isCheckoutConfirmationPage() {
    // @see WGM_Template::change_order_button_text()
    $check_id = get_option('woocommerce_check_page_id');
    if (function_exists('icl_object_id')) {
      $check_id = icl_object_id($check_id);
    }
    $is_confirm_and_place_order_page = get_the_ID() == $check_id;
    return $is_confirm_and_place_order_page;
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
      if ($subscriber_id = get_user_meta(get_current_user_ID(), 'billing_subscriber_id', TRUE)) {
        $fields['billing_subscriber_id']['required'] = TRUE;
        $fields['billing_subscriber_id']['custom_attributes']['readonly'] = 'readonly';
      }
    }
    elseif (!WooCommerce::isCheckoutConfirmationPage()) {
      $fields['billing_email']['label'] .= static::REMOVE_FIELD_CHECKOUT_SECTION_PREFIX;
    }

    if (WooCommerce::isCheckoutConfirmationPage()) {
      // Only output subscriber ID here.
    }
    elseif (($subscriber = WC()->session->get('subscriber_data')) || !empty($subscriber_id)) {
      if (empty($subscriber) && !empty($subscriber_id)) {
        $subscriber = [
          'abono' => $subscriber_id,
          'salutation' => get_user_meta(get_current_user_ID(), 'billing_salutation', TRUE),
          'lastname' => get_user_meta(get_current_user_ID(), 'billing_last_name', TRUE),
          'company_contact' => get_user_meta(get_current_user_ID(), 'billing_company_contact', TRUE),
        ];
      }
      if ($subscriber['salutation'] === 'Firma') {
        $greeting = $subscriber['company_contact'];
      }
      else {
        $greeting = $subscriber['salutation'] . ' ' . $subscriber['lastname'];
      }
      $greeting = esc_html('#' . $subscriber['abono'] . ': ' . $greeting);
      $fields['subscriber_associate'] = [
        'type' => 'checkbox',
        'label' => 'Ich bin bereits Kunde' . ': <strong>' . $greeting . '</strong>',
        'priority' => -100,
        'default' => 1,
      ];

      // @see WooCommerce::woocommerce_checkout_billing_pre()
      unset($fields['billing_subscriber_id']);
    }
    elseif (!is_user_logged_in() || empty($subscriber_id)) {
      $has_associate_values = !empty($_POST) ? !empty($_POST['subscriber_associate']) : WC()->session->get('subscriber_associate');
      $fields['subscriber_associate'] = [
        'type' => 'checkbox',
        'label' => 'Ich bin bereits Kunde',
        'priority' => $subscriber_priority = -100,
        'default' => $has_associate_values,
      ];
      $subscriber_fields = ['subscriber_id', 'postcode', 'salutation', 'first_name', 'last_name', 'company', 'company_contact'];
      foreach ($subscriber_fields as $key) {
        $field = ($key === 'subscriber_id' ? 'billing_' : 'subscriber_') . $key;
        $fields[$field] = $fields['billing_' . $key];
        $fields[$field]['label'] .= static::REMOVE_FIELD_CHECKOUT_SECTION_PREFIX;
        $fields[$field]['required'] = $has_associate_values;
        $subscriber_priority += 10;
        $fields[$field]['priority'] = $subscriber_priority;
        if ($value = !empty($_POST) ? ($_POST[$field] ?? NULL) : WC()->session->get($field)) {
          $fields[$field]['default'] = $value;
        }
      }
      // Prefill user input in billing fields, too.
      $prefill_fields = ['salutation', 'first_name', 'last_name', 'company', 'company_contact'];
      foreach ($prefill_fields as $field) {
        $field = 'billing_' . $field;
        if ($value = !empty($_POST) ? ($_POST[$field] ?? NULL) : WC()->session->get($field)) {
          $fields[$field]['default'] = $value;
        }
      }
      // Require a company name if salutation has been set to company.
      if ($has_associate_values) {
        $fields = WooCommerce::adjustCompanyFields($fields, 'subscriber');
      }
    }

    // Require a company name if salutation has been set to company.
    $fields = WooCommerce::adjustCompanyFields($fields, 'billing');

    // When attempting to associate an existing subscription in the user account,
    // check whether the given subscriber ID matches the registered address.
    // The current hook is invoked more than once during a form submission, so
    // ensure to run this only once, as POST data is manipulated.
    if (!static::$isSubscriberAssociationProcessed && !empty($_POST['subscriber_associate_submit']) && get_query_var('address') === 'billing') {
      foreach ($fields as $key => $field) {
        if (strpos($key, 'subscriber') === FALSE) {
          // The address data returned by alfa may be incomplete; save nevertheless.
          $fields[$key]['required'] = FALSE;

          // Ensure that there are no values, as they will only be set when empty.
          unset($_POST['billing_' . $key]);
        }
      }
      WooCommerce::woocommerce_checkout_process();
    }

    return $fields;
  }

  /**
   * @implements woocommerce_form_field_args
   */
  public static function woocommerce_form_field_args($field) {
    if (isset($field['label']) && FALSE !== strpos($field['label'], static::REMOVE_FIELD_CHECKOUT_SECTION_PREFIX)) {
      $field['label'] = strtr($field['label'], [
        static::REMOVE_FIELD_CHECKOUT_SECTION_PREFIX => '',
      ]);
    }
    return $field;
  }

  /**
   * @implements woocommerce_checkout_required_field_notice
   */
  public static function woocommerce_checkout_required_field_notice($notice) {
    if (FALSE !== strpos($notice, static::REMOVE_FIELD_CHECKOUT_SECTION_PREFIX)) {
      $notice = strtr($notice, [
        static::REMOVE_FIELD_CHECKOUT_SECTION_PREFIX => '',
        // @see WC_Checkout::validate_posted_data()
        str_replace('%s', '', __('Billing %s', 'woocommerce')) => '',
        str_replace('%s', '', __('Shipping %s', 'woocommerce')) => '',
      ]);
    }
    return $notice;
  }

  /**
   * @implements woocommerce_after_checkout_billing_form
   * @implements woocommerce_after_edit_address_form_billing
   */
  public static function woocommerce_after_checkout_billing_form() {
    $form = ob_get_clean();
    $is_subscriber = WC()->session->get('subscriber_data') || (($user_id = get_current_user_ID()) && get_user_meta($user_id, 'billing_subscriber_id', TRUE));
    if ($is_subscriber) {
      $form = str_replace('name="subscriber_associate"', 'name="subscriber_associate" checked required disabled', $form);
    }
    if ($is_address_form = get_query_var('address') === 'billing') {
      $form = preg_replace('@\s+</div>\s*$@', '', $form);
    }
    echo $form;
    if (!$is_subscriber) {
      $name = is_checkout() ? 'woocommerce_checkout_place_order' : 'subscriber_associate_submit';
      echo '<p id="subscriber_submit_field" class="form-row form-actions" data-priority="-20">';
      echo '<button id="subscriber_associate_submit" type="submit" name="' . $name . '" value="1" class="button button--primary">';
      echo 'Meine bestehenden Daten verwenden';
      echo '</button>';
      echo '</p>';
    }
    if ($is_address_form) {
      echo '</div>';
    }
  }

  /**
   * @implements woocommerce_checkout_process
   *
   * Normally, woocommerce_checkout_posted_data would be the proper hook to
   * assign field values, but it is invoked _after_ woocommerce_billing_fields
   * and woocommerce_checkout_fields, where we need to set the 'default' value
   * properties for our custom fields (because WooCommerce does not store nor
   * populate custom address field values in the session for anonymous users),
   * which is a race condition.
   *
   * Theoretically, it should work in the following constellation, but it did
   * not at the time of this writing:
   *
   * - woocommerce_billing_fields defines the additional fields.
   * - woocommerce_checkout_posted_data processes submitted POST data into
   *   customer meta data values using WC()->customer->update_meta_data().
   * - The built-in checkout templates use WC()->checkout->get_value() to
   *   retrieve the value from the customer meta for all fields.
   *
   * The custom meta data values do not appear to be saved to the user session.
   *
   * @see WooCommerce::woocommerce_checkout_fields()
   * @see WooCommerce::woocommerce_checkout_get_value()
   * @see WC_Checkout::get_value()
   */
  public static function woocommerce_checkout_process() {
    if (static::$isSubscriberAssociationProcessed) {
      return;
    }
    static::$isSubscriberAssociationProcessed = TRUE;

    // Set this once upfront to make it available to all validation/processing.
    static::$isFinalCheckoutSubmission = empty($_POST['step']);

    // Do not interfere with earlier checkout form steps.
    if (empty($_POST['subscriber_associate'])) {
      return;
    }
    // Do not overwrite successfully associated subscriber data.
    if (WC()->session->get('subscriber_data')) {
      return;
    }
    $is_checkout = is_checkout();
    // WooCommerce does not retain values of custom fields.
    // Not anchored at front to also save the value of billing_subscriber_id.
    foreach (preg_grep('@subscriber_@', array_keys($_POST)) as $key) {
      WC()->session->set($key, $_POST[$key]);
    }

    $response = WooCommerce::validateSubscriberId();
    Server::addDebugMessage();
    if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
      $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
      wc_add_notice($message, 'error');
    }
    else {
      // Store the result for usage in alfa purchase info instead of the actually
      // submitted form data (so that alfa maps the subscriber ID to the address)
      // and as a marker to hide the subscriber association form elements in the
      // checkout (in case the user goes back).
      // @see WooCommerce::woocommerce_checkout_order_processed()
      WC()->session->set('subscriber_data', $response += [
        'abono' => $_POST['billing_subscriber_id'],
        'firstname' => $_POST['subscriber_first_name'],
        'lastname' => $_POST['subscriber_last_name'],
        'company' => $_POST['subscriber_company'],
        'company_contact' => $_POST['subscriber_company_contact'],
      ]);

      $prefill_fields = [
        'street' => 'address_1',
        'zipcode' => 'postcode',
        'city' => 'city',
        // @todo Country code mapping.
        'country' => 'country',
        'phone' => 'phone',
      ];
      foreach ($prefill_fields as $userinfo_key => $field) {
        if (empty($_POST['billing_' . $field])) {
          // WC_Checkout::get_value() consumes the POST value if there is one.
          $_POST['billing_' . $field] = $response[$userinfo_key];
          if ($is_checkout) {
            WC()->customer->{"set_billing_{$field}"}($response[$userinfo_key]);
          }
        }
      }
      $prefill_fields = [
        'salutation' => 'salutation',
        'housenr' => 'house_number',
        'phone_prefix' => 'phone_prefix',
      ];
      foreach ($prefill_fields as $userinfo_key => $field) {
        if (empty($_POST['billing_' . $field])) {
          $_POST['billing_' . $field] = $response[$userinfo_key];
          if ($is_checkout) {
            WC()->session->set('billing_' . $field, $response[$userinfo_key]);
          }
        }
      }
      if ($response['salutation'] === 'Firma') {
        $greeting = $response['company_contact'];
      }
      else {
        $greeting = $response['salutation'] . ' ' . $response['lastname'];
      }
      wc_add_notice(sprintf('<strong>Willkommen zurück, %s!</strong>', $greeting), 'success');

      // Reload the checkout page to populate the address fields.
      if ($is_checkout) {
        WC()->session->set('reload_checkout', TRUE);
      }
    }
    // Also take over the user input into corresponding fields.
    $prefill_fields = ['first_name', 'last_name', 'company'];
    foreach ($prefill_fields as $field) {
      if (empty($_POST['billing_' . $field])) {
        $_POST['billing_' . $field] = $_POST['subscriber_' . $field];
        if ($is_checkout) {
          WC()->customer->{"set_billing_{$field}"}($_POST['subscriber_' . $field]);
        }
      }
    }
    $prefill_fields = ['salutation', 'company_contact'];
    foreach ($prefill_fields as $field) {
      if (empty($_POST['billing_' . $field])) {
        $_POST['billing_' . $field] = $_POST['subscriber_' . $field];
        if ($is_checkout) {
          WC()->session->set('billing_' . $field, $_POST['subscriber_' . $field]);
        }
      }
    }

    // Save all additions.
    if ($is_checkout) {
      WC()->customer->save();
    }
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
    $value = !empty($_POST) && isset($_POST[$address_type . '_salutation']) ? $_POST[$address_type . '_salutation'] : WC()->session->get($address_type . '_salutation');
    if ($value === 'Firma') {
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
   * @implements woocommerce_de_add_review_order
   */
  public static function woocommerce_de_add_review_order() {
    // The advertising opt-in should only be displayed when not opt-in yet.
    $optins = get_user_meta(get_current_user_ID(), 'optins', TRUE);
    if (empty($optins['acquisitionEmail'])) {
      woocommerce_form_field('acquisitionEmail', WooCommerce::OPTINS['acquisitionEmail'] + [
        'type' => 'checkbox',
      ]);
    }
  }

  /**
   * @implements woocommerce_localisation_address_formats
   */
  public static function woocommerce_localisation_address_formats($formats) {
    foreach ($formats as $country => $value) {
      $formats[$country] .= "\n{phone}";
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
    if (!empty($fields['salutation']) && $fields['salutation'] === 'Firma') {
      $fields['salutation'] = '';
      $replacements['{name}'] = $fields['company_contact'];
    }
    $replacements['{salutation}'] = !empty($fields['salutation']) ? $fields['salutation'] . ' ' : '';
    $replacements['{house_number}'] = !empty($fields['house_number']) ? ' ' . $fields['house_number'] : '';
    $replacements['{phone}'] = !empty($fields['phone']) ? $fields['phone'] . ' ' : '';
    $replacements['{phone_prefix}'] = !empty($fields['phone_prefix']) ? 'Telefon: ' . $fields['phone_prefix'] . '-' : '';
    return $replacements;
  }

  /**
   * @implements woocommerce_customer_get_<$prop>
   *
   * @see WC_Data::get_prop()
   */
  public static function woocommerce_customer_get_address($values, $customer) {
    $type = explode('_', current_filter());
    $type = array_pop($type);
    if (!$salutation = $customer->get_meta($type . '_salutation')) {
      $salutation = $customer->get_meta('_' . $type . '_salutation');
    }
    if (!$company_contact = $customer->get_meta($type . '_company_contact')) {
      $company_contact = $customer->get_meta('_' . $type . '_company_contact');
    }
    if (!$house_number = $customer->get_meta($type . '_house_number')) {
      $house_number = $customer->get_meta('_' . $type . '_house_number');
    }
    if (!$phone_prefix = $customer->get_meta($type . '_phone_prefix')) {
      $phone_prefix = $customer->get_meta('_' . $type . '_phone_prefix');
    }
    $values += [
      'salutation' => $salutation,
      'company_contact' => $company_contact,
      'house_number' => $house_number,
      'phone_prefix' => $phone_prefix,
    ];
    return $values;
  }

  /**
   * @implements woocommerce_<$object_type>_get_<$prop>
   *
   * @see WC_Data::get_prop()
   */
  public static function woocommerce_order_get_billing_phone($value, $object_type) {
    // Return empty as we added the field to the address format already.
    return '';
  }

  /**
   * Validates checkout fields against SSO.
   *
   * @implements woocommerce_after_checkout_validation
   */
  public static function woocommerce_after_checkout_validation($data, $errors) {
    // Verifies that the given email address is NOT registered yet.
    // Note: The API endpoint checks the opposite; a positive result is an error.
    // @todo Handle email change for logged-in users.
    if (!empty($_POST['billing_email']) && !is_user_logged_in()) {
      $response = Server::isEmailRegistered($_POST['billing_email']);
      // Error 607: "Given email is unknown" is the only allowed positive case.
      if (!isset($response['statuscode']) || $response['statuscode'] !== 607) {
        $message = isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.');
        $errors->add('billing_email', $message);
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
   * Prevents checkout form submission if a specific step is requested.
   *
   * @implements woocommerce_after_checkout_validation
   */
  public static function woocommerce_after_checkout_validation_multistep($data, $errors) {
    // WC_Checkout::update_session() only stores a minimal list of address fields
    // for the customer in the session, so all other values are lost in case the
    // page is refreshed. Fix this by storing all values.
    // @todo Fix this upstream in WooCommerce Core.
    $customer = WC()->customer;
    $session = WC()->session;
    $is_authenticated = (bool) get_current_user_ID();
    foreach ($data as $field => $value) {
      if (!$is_authenticated && is_callable([$customer, "set_{$field}"])) {
        $customer->{"set_{$field}"}($value);
      }
      else {
        $session->set($field, $value);
      }
    }
    WC()->customer->save();
  }

  /**
   * Validates subscriber ID association user input.
   */
  public static function validateSubscriberId($address_type = 'subscriber') {
    static $response;
    if (isset($response)) {
      return $response;
    }
    if (isset($_POST[$address_type . '_salutation']) && $_POST[$address_type . '_salutation'] === 'Firma') {
      $response = Server::checkSubscriberId(
        $_POST['billing_subscriber_id'],
        '',
        $_POST[$address_type . '_company'] ?? '',
        $_POST[$address_type . '_postcode'] ?? ''
      );
    }
    else {
      $response = Server::checkSubscriberId(
        $_POST['billing_subscriber_id'],
        $_POST[$address_type . '_first_name'] ?? '',
        $_POST[$address_type . '_last_name'] ?? '',
        $_POST[$address_type . '_postcode'] ?? ''
      );
    }
    return $response;
  }

  /**
   * Submits purchase to SSO.
   *
   * @implements woocommerce_checkout_order_processed
   */
  public static function woocommerce_checkout_order_processed($order_id, $posted_data, $order) {
    global $woocommerce;

    // Do not attempt to register purchase in case of a validation error.
    if (wc_notice_count('error') || empty($_POST['woocommerce_checkout_place_order'])) {
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
      if ($subscriber = WC()->session->get('subscriber_data')) {
        $replace_fields = [
          'salutation',
          'company',
          // 'title',
          'firstname',
          'lastname',
          'street',
          'housenr',
          'zipcode',
          'city',
          'country',
          // 'birthday',
          'phone_prefix',
          'phone',
        ];
        foreach ($replace_fields as $key) {
          if (isset($subscriber[$key])) {
            $purchase[$key] = $subscriber[$key];
          }
          else {
            unset($purchase[$key]);
          }
        }
      }
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
    // Note: If the subscriber data is not valid, a form validation error is
    // output via WooCommerce::validateSubscriberId() resp.
    // WooCommerce::woocommerce_checkout_process() already.
    // @see WooCommerce::woocommerce_billing_fields()

    // Remove subscriber associate fields from POST data to prevent the user input
    // from being saved to user meta.
    foreach (['associate', 'postcode', 'salutation', 'first_name', 'last_name', 'company', 'company_contact'] as $key) {
      unset($_POST['subscriber_' . $key]);
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

    $response = Server::updateUser($userinfo);
    if (!isset($response['statuscode']) || $response['statuscode'] !== 200) {
      wc_add_notice(isset($response['userMessages']) ? implode('<br>', $response['userMessages']) : __('Error while saving the changes.'), 'error');
      Server::addDebugMessage();
    }
    else {
      // Remove subscriber validation response data from session, so that the
      // information in the user profile is based on the actual user profile data.
      if (WC()->session->get('subscriber_data')) {
        WC()->session->set('subscriber_data', '');
      }

      // Save the subscriber ID returned by alfa (replacing the user input).
      if (!empty($response['aboNo'])) {
        $_POST['billing_subscriber_id'] = $response['aboNo'];
      }
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

    // Save opt-ins manually, as new UserInfo is only retrieved with next login.
    $optins = get_user_meta(get_current_user_ID(), 'optins', TRUE);
    foreach (WooCommerce::OPTINS as $optin_name => $definition) {
      if (isset($_POST[$optin_name])) {
        $optins[$optin_name] = $_POST[$optin_name];
      }
    }
    update_user_meta(get_current_user_ID(), 'optins', $optins);
  }

  /**
   * @implements woocommerce_save_account_details
   */
  public static function woocommerce_save_account_details_redirect($user_id) {
    // Stay on the account edit form page.
    wp_safe_redirect(wc_get_account_endpoint_url('edit-account'));
    exit;
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

    $optins = get_user_meta(get_current_user_ID(), 'optins', TRUE);
    foreach (static::OPTINS as $optin_name => $definition) {
      // The acquisition opt-ins should only appear during checkout.
      if (strpos($optin_name, 'acquisition') === 0) {
        continue;
      }
      woocommerce_form_field($optin_name, $definition + [
        'type' => 'checkbox',
        'default' => $optins[$optin_name] ?? 0,
      ]);
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
    if ($template_name === 'myaccount/form-lost-password.php' || $template_name === 'global/form-login.php' || $template_name === 'myaccount/my-subscriptions.php') {
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
    if ($template_name === 'global/form-login.php') {
      $output = ob_get_clean();
      $output = strtr($output, [
        '<form class="woocommerce-form woocommerce-form-login' => '<div class="woocommerce-account-fields woocommerce-form woocommerce-form-login',
        '</form>' => '</div>',
      ]);
      echo $output;
    }
    elseif ($template_name === 'myaccount/my-subscriptions.php') {
      $output = ob_get_clean();
      echo WooCommerce::viewSubscription();
      // echo $output;
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
    Alfa::renderPurchases(Alfa::getPurchases(NULL, TRUE));
    // Alfa::mapPurchases(Alfa::getPurchasesFlattened());
  }

  /**
   * Copies custom checkout fields into order data.
   *
   * @implements woocommerce_checkout_update_order_meta
   */
  public static function woocommerce_checkout_update_order_meta($order_id) {
    $fields = [
      'billing_subscriber_id',
      'billing_salutation',
      'billing_company_contact',
      'billing_house_number',
      'billing_phone_prefix',
      'shipping_salutation',
      'shipping_company_contact',
      'shipping_house_number',
      'payment_interval',
    ];
    foreach ($fields as $field) {
      if (!empty($_POST[$field])) {
        update_post_meta($order_id, $field, sanitize_text_field($_POST[$field]));
      }
    }
  }

  /**
   * Displays subscriber ID in new order notification email.
   *
   * @woocommerce_email_order_meta
   */
  public static function woocommerce_email_order_meta($order) {
    $user_id = $order->get_user_id();
    if ($subscriber_id = get_user_meta($user_id, 'billing_subscriber_id', TRUE)) {
      echo '<p><strong>' . __('Subscription ID:', PLUGIN::L10N) . '</strong> ' . $subscriber_id . '</p>';
    }

    $payment_interval = get_post_meta($order->get_id(), 'payment_interval', TRUE);
    if ($payment_interval && isset(static::PAYMENT_INTERVALS[$payment_interval])) {
      echo '<p><strong>' . __('Payment interval', Plugin::L10N) . ':</strong> ' . __(static::PAYMENT_INTERVALS[$payment_interval], Plugin::L10N) . '</p>';
    }

    if (!isset($_POST['optins'])) {
      return;
    }
    $optins_list = '';
    foreach (static::OPTINS as $opt_in_id => $opt_in_args) {
      if (!isset($_POST['optins'][$opt_in_id])) {
        continue;
      }
      $optins_list .= '<span class="optin-label"><strong>' . $opt_in_args['label'] . ':</strong></span> ';
      $optins_list .= '<span class="optin-value">' . ($_POST['optins'][$opt_in_id] ? __('Yes', 'woocommerce') : __('No', 'woocommerce')) . '</span><br />';
    }
    echo '<p>' . $optins_list . '</p>';
  }

  /**
   * Displays the selected payment interval value in the order confirmation page.
   *
   * @implements woocommerce_checkout_after_customer_details
   */
  public static function woocommerce_checkout_after_customer_details() {
    $output = ob_get_clean();
    $matches = [];
    $pattern = '@(<span class="wgm-field-label">' . __('Payment interval', Plugin::L10N) . '</span></td><td>)(.+)(</td>)@';
    preg_match($pattern, $output, $matches);
    if ($matches && isset(static::PAYMENT_INTERVALS[$matches[2]])) {
      $payment_interval = __(static::PAYMENT_INTERVALS[$matches[2]], Plugin::L10N);
      $output = preg_replace($pattern, '$1' . $payment_interval . '$3', $output);
    }
    echo $output;
  }

}
