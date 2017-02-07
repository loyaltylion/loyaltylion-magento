<?php

class LoyaltyLion_Client {

  private $token;
  private $secret;
  private $connection;
  private $base_uri = 'https://api.loyaltylion.com/v2';

  public function __construct($token, $secret, $extra = array()) {
    $this->token = $token;
    $this->secret = $secret;

    if (empty($this->token) || empty($this->secret)) {
      throw new Exception("Please provide a valid token and secret (token: ${token}, secret: ${secret})");
    }

    if (isset($extra['base_uri'])) $this->base_uri = $extra['base_uri'];

    $this->connection = new LoyaltyLion_Connection($this->token, $this->secret, $this->base_uri);

    $this->activities = $this->events = new LoyaltyLion_Activities($this->connection);
    $this->orders = new LoyaltyLion_Orders($this->connection);
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
