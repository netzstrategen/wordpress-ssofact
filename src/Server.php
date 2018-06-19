<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\Server.
 */

namespace Netzstrategen\Ssofact;

class Server {

  /**
   * @var string
   */
  const ENDPOINT_IS_EMAIL_REGISTERED = '/REST/services/authenticate/user/IsEmailRegistered';

  /**
   * @var string
   */
  const ENDPOINT_SUBSCRIPTION_NUMBER = '/REST/services/authenticate/user/checkAboNo';

  /**
   * @var string
   */
  const ENDPOINT_USER_UPDATE = '/REST/services/authenticate/user/updateUser';

  /**
   * @var string
   */
  const ENDPOINT_PURCHASE_CREATE = '/REST/services/authorize/purchase/registerPurchase';

  /**
   * Checks if the email is already registered.
   *
   * @param string email
   *
   * @return null|array
   */
  public static function isEmailRegistered($email) {
    $api_url = 'https://' . SSOFACT_SERVER_DOMAIN . static::ENDPOINT_IS_EMAIL_REGISTERED;
    $response = wp_remote_post($api_url, [
      'body' => [
        'email' => $email
      ],
      'headers' => [
        'Accept' => 'application/json',
        'rfbe-key' => SSOFACT_RFBE_KEY,
        'rfbe-secret' => SSOFACT_RFBE_SECRET,
      ],
    ]);
    if ($response instanceof \WP_Error) {
      static::triggerCommunicationError();
      return;
    }
    return json_decode($response['body'], JSON_OBJECT_AS_ARRAY);
  }

  /**
   * Checks if the subscription ID number matches the user full name and zipcode.
   *
   * @param int subscriptionId
   * @param string firstName
   * @param string lastName
   * @param int zipCode
   *
   * @return null!array
   */
  public static function checkSubscriptionNumber($subscriptionId, $firstName, $lastName, $zipCode) {
    $api_url = 'https://' . SSOFACT_SERVER_DOMAIN . static::ENDPOINT_SUBSCRIPTION_NUMBER;
    $response = wp_remote_post($api_url, [
      'body' => [
        "abono" => $subscriptionId,
        "firstname" => $firstName,
        "lastname" => $lastName,
        "zipcode" => $zipCode,
      ],
      'headers' => [
        'Accept' => 'application/json',
        'rfbe-key' => SSOFACT_RFBE_KEY,
        'rfbe-secret' => SSOFACT_RFBE_SECRET,
      ],
    ]);
    if ($response instanceof \WP_Error) {
      static::triggerCommunicationError();
      return;
    }
    return json_decode($response['body'], JSON_OBJECT_AS_ARRAY);
  }

  /**
   * Updates the info of a user.
   *
   * @param array $userinfo
   *   The new user info to set.
   *
   * @return null|array
   */
  public static function updateUser(array $userinfo) {
    if (!isset($userinfo['id'])) {
      throw new \InvalidArgumentException('Missing user ID to update.');
    }
    $api_url = 'https://' . SSOFACT_SERVER_DOMAIN . static::ENDPOINT_USER_UPDATE;
    $response = wp_remote_post($api_url, [
      'body' => json_encode($userinfo),
      'headers' => [
        'Accept' => 'application/json',
        'rfbe-key' => SSOFACT_RFBE_KEY,
        'rfbe-secret' => SSOFACT_RFBE_SECRET,
      ],
    ]);
    if ($response instanceof \WP_Error) {
      static::triggerCommunicationError();
      return;
    }
    return json_decode($response['body'], JSON_OBJECT_AS_ARRAY);
  }

  /**
   * Creates a purchase for a user.
   *
   * @param array $purchase
   *   The purchase data to register; see Plugin::buildPurchaseInfo().
   *
   * @return null|array
   */
  public static function registerPurchase(array $purchase) {
    if (!isset($purchase['id'])) {
      throw new \InvalidArgumentException('Missing user ID to update.');
    }
    $api_url = 'https://' . SSOFACT_SERVER_DOMAIN . static::ENDPOINT_PURCHASE_CREATE;
    $response = wp_remote_post($api_url, [
      'body' => json_encode($purchase),
      'headers' => [
        'Accept' => 'application/json',
        'rfbe-key' => SSOFACT_RFBE_KEY,
        'rfbe-secret' => SSOFACT_RFBE_SECRET,
      ],
    ]);
    if ($response instanceof \WP_Error) {
      static::triggerCommunicationError();
      return;
    }
    return json_decode($response['body'], JSON_OBJECT_AS_ARRAY);
  }

  /**
   * Triggers WooCommerce error message if SSO server does not respond.
   */
  public static function triggerCommunicationError() {
    wp_send_json([
      'result' => 'failure',
      'messages' => wc_add_notice(__('An error occurred while processing your data. Please try again in a few minutes.', Plugin::L10N), 'error'),
      'reload' => TRUE,
    ]);
    trigger_error($response->get_error_message(), E_USER_ERROR);
  }

}
