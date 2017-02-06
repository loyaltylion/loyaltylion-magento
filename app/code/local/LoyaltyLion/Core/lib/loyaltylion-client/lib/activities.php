<?php

class LoyaltyLion_Activities extends LoyaltyLion_Client {

  public function __construct($connection) {
    $this->connection = $connection;
  }

  /**
   * Track an activity
   * 
   * @param  [type] $name             The activity name, e.g. "$signup"
   * @param  array  $properties       Activity data
   * 
   * @return object                   An object with information about the request. If the track 
   *                                  was successful, object->success will be true.
   */
  public function track($name, $data) {

    if (!is_array($data)) throw new Exception('Activity data must be an array');

    $data['name'] = $name;

    if (empty($data['name'])) throw new Exception('Activity name is required');
    if (empty($data['customer_id'])) throw new Exception('customer_id is required');
    if (empty($data['customer_email'])) throw new Exception('customer_email is required');

    if (empty($data['date'])) $data['date'] = date('c');

    $response = $this->connection->post('/activities', $data);

    return $this->parseResponse($response);
  }

  /**
   * Update an activity using its merchant_id
   * 
   * @param  [type] $name [description]
   * @param  [type] $id   [description]
   * @param  [type] $data [description]
   * @return [type]       [description]
   */
  public function update($name, $id, $data) {
    $response = $this->connection->put('/activities/' . $name . '/' . $id, $data);

    return $this->parseResponse($response);
  }
}
