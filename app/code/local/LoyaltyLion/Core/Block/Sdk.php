<?php

class LoyaltyLion_Core_Block_Sdk extends Mage_Core_Block_Template {
  
  public function isEnabledForCurrentContext() {
    $token = $this->getToken();
    $secret = $this->getSecret();

    if (empty($token) || empty($secret)) return false;
    return true;
  }

  public function getToken() {
    return Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_token');
  }

  public function getSecret() {
    return Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_secret');
  }

  public function getLoaderUrl() {
    return $this->getSdkHost() . $this->getLoaderPath();
  }

  private function getLoaderPath() {
    return isset($_SERVER['LOYALTYLION_LOADER_PATH']) ? $_SERVER['LOYALTYLION_LOADER_PATH'] : '/static/2/loader.js';
  }

  public function getSdkHost() {
    return isset($_SERVER['LOYALTYLION_SDK_HOST']) ? $_SERVER['LOYALTYLION_SDK_HOST'] : 'sdk.loyaltylion.net';
  }
}
