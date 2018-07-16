<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\Server.
 */

namespace Netzstrategen\Ssofact;

class Server {

  const ENDPOINT_IS_EMAIL_REGISTERED = '/REST/services/authenticate/user/IsEmailRegistered';

  const ENDPOINT_LOGIN_VALIDATE = '/REST/services/authenticate/user/checkUserCredentials';

  const ENDPOINT_SUBSCRIBER_ID = '/REST/services/authenticate/user/checkAboNo';

  const ENDPOINT_USER_UPDATE = '/REST/services/authenticate/user/updateUser';

  const ENDPOINT_USER_CREATE_WITH_PURCHASE = '/REST/services/authorize/purchase/registerUserAndPurchase';

  const ENDPOINT_PURCHASE_CREATE = '/REST/services/authorize/purchase/registerPurchase';

  private static $debugLog = [];

  /**
   * Checks whether the given email address is already registered.
   *
   * @param string $email
   *   The email address to check.
   *
   * @return null|array
   */
  public static function isEmailRegistered($email) {
    $response = Server::request('POST', static::ENDPOINT_IS_EMAIL_REGISTERED, ['email' => $email]);
    return $response;
  }

  /**
   * Checks whether the given user login credentials are correct.
   *
   * @param string $login
   *   The email address of the user account to check.
   * @param string $password
   *   The current password of the user account to check.
   *
   * @return null|array
   */
  public static function validateLogin($login, $password) {
    $response = Server::request('POST', static::ENDPOINT_LOGIN_VALIDATE, [
      'login' => $login,
      'pass' => $password,
    ]);
    return $response;
  }

  /**
   * Returns whether the subscriber ID matches the name and zipcode.
   *
   * @return null!array
   */
  public static function checkSubscriberId($subscriber_id, $first_name, $last_name, $zip_code) {
    $response = Server::request('POST', static::ENDPOINT_SUBSCRIBER_ID, [
      'abono' => $subscriber_id,
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
      'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
      'timeout' => 30,
    ];
    $response = wp_remote_request($api_url, $request);
    if ($response instanceof \WP_Error) {
      static::triggerCommunicationError($request, $response);
      $response = (array) $response;
    }
    else {
      $response = json_decode($response['body'], JSON_OBJECT_AS_ARRAY);
    }
    static::$debugLog[] = [
      'request' => ['body' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)] + $request,
      'response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    ];
    return $response;
  }

  /**
   * Triggers WooCommerce error message if SSO server does not respond.
   */
  public static function triggerCommunicationError($request, $response) {
    wc_add_notice(__('An error occurred while processing your data. Please try again in a few minutes.', Plugin::L10N), 'error');
  }

  /**
   * Adds WooCommerce notice with debug information if WP_DEBUG is enabled.
   */
  public static function addDebugMessage() {
    if (WP_DEBUG) {
      $debug_request = '';
      foreach (static::$debugLog as $request) {
        $debug_request .= '<details>';
        $debug_request .= '<summary>';
        $debug_request .= $request['request']['method'] . ' ' . $request['request']['url'] . "\n";
        $debug_request .= '</summary>';
        $debug_request .= "<pre>\n";
        foreach ($request['request']['headers'] as $key => $value) {
          $debug_request .= "$key: $value\n";
        }
        $debug_request .= $request['request']['body'] . "\n";
        $debug_request .= $request['response'];
        $debug_request .= "\n</pre>";
        $debug_request .= '</details>';
      }
      static::$debugLog = [];
      return wc_add_notice($debug_request, 'notice');
    }
  }

}
