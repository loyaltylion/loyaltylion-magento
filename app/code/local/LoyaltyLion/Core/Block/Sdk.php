<?php

class LoyaltyLion_Core_Block_Sdk extends Mage_Core_Block_Template {
  
  public function isEnabled() {
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

  public function getSDKUrl() {
    return isset($_SERVER['LOYALTYLION_SDK_URL']) ? $_SERVER['LOYALTYLION_SDK_URL'] : 'dg1f2pfrgjxdq.cloudfront.net/libs/ll.sdk-1.1.js';
  }

  public function getPlatformHost() {
    return isset($_SERVER['LOYALTYLION_PLATFORM_HOST']) ? $_SERVER['LOYALTYLION_PLATFORM_HOST'] : 'platform.loyaltylion.com';
  }

  // Used to set the cache placeholder attribute definitions, required in
  // the placeholder's "_renderBlock" function.
  public function getCacheKeyInfo() {
	  return array('block_id' => $this->getBlockId());
  }
}
