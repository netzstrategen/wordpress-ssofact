<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\Server.
 */

namespace Netzstrategen\Ssofact;

class Server {

  const ENDPOINT_IS_EMAIL_REGISTERED = '/REST/services/authenticate/user/IsEmailRegistered';

  const ENDPOINT_SUBSCRIPTION_NUMBER = '/REST/services/authenticate/user/checkAboNo';

  const ENDPOINT_USER_UPDATE = '/REST/services/authenticate/user/updateUser';

  const ENDPOINT_USER_CREATE_WITH_PURCHASE = '/REST/services/authorize/purchase/registerUserAndPurchase';

  const ENDPOINT_PURCHASE_CREATE = '/REST/services/authorize/purchase/registerPurchase';

  private static $debugLog = [];

  /**
   * Checks if the email is already registered.
   *
   * @param string email
   *
   * @return null|array
   */
  public static function isEmailRegistered($email) {
    $response = Server::request('POST', static::ENDPOINT_IS_EMAIL_REGISTERED, ['email' => $email]);
    // @todo Fix bogus nested list in server response.
    if (isset($response[0])) {
      $response = $response[0];
    }
    return $response;
  }

  /**
   * Returns whether the subscription ID matches the name and zipcode.
   *
   * @return null!array
   */
  public static function checkSubscriptionNumber($subscription_id, $first_name, $last_name, $zip_code) {
    $response = Server::request('POST', static::ENDPOINT_SUBSCRIPTION_NUMBER, [
      'abono' => $subscription_id,
      'firstname' => $first_name,
      'lastname' => $last_name,
      'zipcode' => $zip_code,
    ]);
    return $response;
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
    $response = Server::request('POST', static::ENDPOINT_USER_UPDATE, $userinfo);
    return $response;
  }

  /**
   * Creates a purchase for a user.
   *
   * @param array $purchase
   *   The purchase data to register; see Plugin::buildPurchaseInfo().
   *
   * @return null|array
   */
  public static function registerUserAndPurchase(array $purchase) {
    if (isset($purchase['id'])) {
      throw new \InvalidArgumentException('Cannot register user: pre-existing user ID.');
    }
    $response = Server::request('POST', static::ENDPOINT_USER_CREATE_WITH_PURCHASE, $purchase);
    return $response;
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
      throw new \InvalidArgumentException('Missing user ID for purchase.');
    }
    $response = Server::request('POST', static::ENDPOINT_PURCHASE_CREATE, $purchase);
    return $response;
  }

  /**
   * Performs a request against ssoFACT server REST API.
   *
   * @param string $method
   *   The HTTP method to use; either 'POST' or 'GET'.
   * @param string $endpoint
   *   The endpoint (path) to request; one of the class constants.
   * @param mixed $data
   *   The data to send.
   *
   * @return null|array
   *   The decoded response or NULL in case of a communication error.
   */
  public static function request($method, $endpoint, $data) {
    $api_url = 'https://' . SSOFACT_SERVER_DOMAIN . $endpoint;
    $request = [
      'method' => $method,
      'url' => $api_url,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'rfbe-key' => SSOFACT_RFBE_KEY,
        'rfbe-secret' => SSOFACT_RFBE_SECRET,
      ],
      'body' => json_encode($data),
    ];
    $response = wp_remote_request($api_url, $request);
    if ($response instanceof \WP_Error) {
      static::triggerCommunicationError();
      return;
    }
    $response = json_decode($response['body'], JSON_OBJECT_AS_ARRAY);

    static::$debugLog[] = [
      'request' => ['body' => json_encode($data, JSON_PRETTY_PRINT)] + $request,
      'response' => json_encode($response, JSON_PRETTY_PRINT),
    ];
    return $response;
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

  /**
   * Adds WooCommerce notice with debug information if WP_DEBUG is enabled.
   */
  public static function addDebugMessage() {
    if (WP_DEBUG) {
      $debug_request = '';
      foreach (static::$debugLog as $request) {
        $debug_request .= $request['request']['method'] . ' ' . $request['request']['url'] . "\n";
        foreach ($request['request']['headers'] as $key => $value) {
          $debug_request .= "$key: $value\n";
        }
        $debug_request .= $request['request']['body'] . "\n";
        $debug_request .= $request['response'];
      }
      static::$debugLog = [];
      return wc_add_notice("<pre>\n$debug_request\n</pre>", 'notice');
    }
  }

}
