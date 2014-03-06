<?php

require('lib/connection.php');

class LoyaltyLion_Client {

  private $token;
  private $secret;
  private $connection;
  private $base_uri = 'http://api.loyaltylion.com/v2';

  public function __construct($token, $secret, $extra = array()) {
    $this->token = $token;
    $this->secret = $secret;

    if (empty($this->token) || empty($this->secret)) {
      throw new Exception("Please provide a valid token and secret (token: ${token}, secret: ${secret})");
    }

    if (isset($extra['base_uri'])) $this->base_uri = $extra['base_uri'];

    $this->connection = new LoyaltyLion_Connection($this->token, $this->secret, $this->base_uri);

    $this->events = new LoyaltyLion_Events($this->connection);
    $this->orders = new LoyaltyLion_Orders($this->connection);
  }

  public function getCustomerAuthToken($customer_id) {
    $params = array(
      'customer_id' => $customer_id,
    );

    $response = $this->connection->post('/customers/authenticate', $params);

    if (isset($response->error)) {
      echo "LoyaltyLion client error: " . $response->error;
    }
    
    // should have got json back
    if (empty($response->body)) return null;

    $json = json_decode($response->body);

    if ($json && $json->auth_token) {
      return $json->auth_token;
    } else {
      return null;
    }
  }

  protected function parseResponse($response) {
    if (isset($response->error)) {
      // this kind of error is from curl itself, e.g. a request timeout, so just return that error
      return (object) array(
        'success' => false,
        'status' => $response->status,
        'error' => $response->error,
      );
    }

    $result = array(
      'success' => (int) $response->status >= 200 && (int) $response->status <= 204
    );

    if (!$result['success']) {
      // even if curl succeeded, it can still fail if the request was invalid - we
      // usually have the error as the body so just stick that in
      $result['error'] = $response->body;
      $result['status'] = $response->status;
    }

    return (object) $result;
  }
}

class LoyaltyLion_Events extends LoyaltyLion_Client {

  public function __construct($connection) {
    $this->connection = $connection;
  }

  /**
   * Track an event
   * 
   * @param  [type] $name             The activity name, as defined by you
   * @param  [type] $customer_id      The ID of the current logged in customer
   * @param  [type] $customer_email   The email of the current logged in customer
   * @param  array  $properties       Activity specific properties
   * @return object                   An object with information about the request. If the track 
   *                                  was successful, object->success will be true.
   */
  public function track($name, $data) {
    $params = array(
      'name' => $name,
      'date' => isset($data['date']) ? $data['date'] : date('c'),
      'customer_id' => $data['customer_id'],
      'customer_email' => $data['customer_email'],
    );

    if (isset($data['properties'])) $params['properties'] = $data['properties'];

    $response = $this->connection->post('/events', $params);

    return $this->parseResponse($response);
  }
}

class LoyaltyLion_Orders extends LoyaltyLion_Client {

  public function __construct($connection) {
    $this->connection = $connection;
  }

  public function create($data) {
    $response = $this->connection->post('/orders', $data);

    return $this->parseResponse($response);
  }

  public function update($id, $data) {
    $response = $this->connection->put('/orders/' . $id, $data);

    return $this->parseResponse($response);
  }

  public function setCancelled($id) {
    $response = $this->connection->put('/orders/' . $id . '/cancelled');

    return $this->parseResponse($response);
  }

  public function setPaid($id) {
    $response = $this->connection->put('/orders/' . $id . '/paid');

    return $this->parseResponse($response);
  }

  public function setRefunded($id) {
    $response = $this->connection->put('/orders/' . $id . '/refunded');

    return $this->parseResponse($response);
  }

  public function addPayment($id, $data) {
    $response = $this->connection->post('/orders/' . $id . '/payments', $data);

    return $this->parseResponse($response);
  }

  public function addRefund($id, $data) {
    $response = $this->connection->post('/orders/' . $id . '/refunds', $data);

    return $this->parseResponse($response);
  }
}