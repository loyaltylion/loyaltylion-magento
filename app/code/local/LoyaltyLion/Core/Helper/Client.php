<?php

if (class_exists('LoyaltyLion_Client', false) == false) {
  require_once( Mage::getModuleDir('', 'LoyaltyLion_Core') . DS . 'lib' . DS . 'loyaltylion-client' . DS . 'main.php' );
}

class LoyaltyLion_Core_Helper_Client extends Mage_Core_Helper_Abstract
{
  private $options = array();

  public function __construct() {
    if (isset($_SERVER['LOYALTYLION_API_BASE'])) {
      $this->options['base_uri'] = $_SERVER['LOYALTYLION_API_BASE'];
    }
  }

  public function client($token, $secret) {
    return $this->client = new LoyaltyLion_Client($token, $secret, $this->options);
  }
}
