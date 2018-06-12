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
      static::displaySsoResponseError();
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
      static::displaySsoResponseError();
      return;
    }
    return json_decode($response['body'], JSON_OBJECT_AS_ARRAY);
  }

  /**
   * Displays woocommerce notice message if the SSO server connection fails.
   *
   * @return null
   */
  public static function displaySsoResponseError() {
    wp_send_json([
      'result' => 'failure',
      'messages' => wc_add_notice(__('An error occurred while processing your data. Please try again in a few minutes.', Plugin::L10N), 'error'),
      'reload' => TRUE,
    ]);
    trigger_error($response->get_error_message(), E_USER_ERROR);
  }

}
