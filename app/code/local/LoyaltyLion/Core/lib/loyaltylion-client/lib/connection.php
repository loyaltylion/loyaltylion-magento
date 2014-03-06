<?php

class LoyaltyLion_Connection {

  private $token;
  private $secret;
  private $auth;
  private $base_uri;
  private $timeout = 5;

  public function __construct($token, $secret, $base_uri) {
    $this->token = $token;
    $this->secret = $secret;
    $this->base_uri = $base_uri;
  }

  public function post($path, $data = array()) {
    return $this->request('POST', $path, $data);
  }

  public function put($path, $data = array()) {
    return $this->request('PUT', $path, $data);
  }

  private function request($method, $path, $data) {

    $options = array(
      CURLOPT_URL => $this->base_uri . $path,
      CURLOPT_USERAGENT => 'loyaltylion-php-client-v2.0.0',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => $this->timeout,
      // CURLOPT_HEADER => false,
      CURLOPT_USERPWD => $this->token . ':' . $this->secret,
    );
    
    switch ($method) {
      case 'POST':
        $options += array(
          CURLOPT_POST => true,
        );
        break;
      case 'PUT':
        $options += array(
          CURLOPT_CUSTOMREQUEST => 'PUT',
        );
    }

    if (!empty($data)) {
      $body = json_encode($data);

      $options += array(
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/json',
          'Content-Length: ' . strlen($body),
        ),
      );
    } 
    
    // now make the request
    $curl = curl_init();
    curl_setopt_array($curl, $options);

    $body = curl_exec($curl);
    $headers = curl_getinfo($curl);
    $error_code = curl_errno($curl);
    $error_msg = curl_error($curl);
    
    if ($error_code !== 0) {
      $response = array(
        'status'  => $headers['http_code'],
        'error' => $error_msg,
      );
    } else {
      $response = array(
        'status' => $headers['http_code'],
        'headers' => $headers,
        'body' => $body,
      );
    }

    return (object) $response;
  }
}