<?php

if (class_exists('LoyaltyLion_Client', false) == false) {
  require_once( Mage::getModuleDir('', 'LoyaltyLion_Core') . DS . 'lib' . DS . 'loyaltylion-client' . DS . 'main.php' );
}

class LoyaltyLion_Core_Helper_Client extends Mage_Core_Helper_Abstract
{
  private $client;
  private $token;
  private $secret;

  public function __construct() {
    $this->token = Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_token');
    $this->secret = Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_secret');

    $options = array();

    if (isset($_SERVER['LOYALTYLION_API_BASE'])) {
      $options['base_uri'] = $_SERVER['LOYALTYLION_API_BASE'];
    }

    $this->client = new LoyaltyLion_Client($this->token, $this->secret, $options);
  }

  public function client() {
    return $this->client;
  }
}
