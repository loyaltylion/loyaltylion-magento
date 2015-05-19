<?php

class LoyaltyLion_Core_Model_Observer {

  private $client;
  private $session;

  public function __construct() {
    $this->token = Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_token');
    $this->secret = Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_secret');

    if (!$this->isEnabled()) return;

    require( Mage::getModuleDir('', 'LoyaltyLion_Core') . DS . 'lib' . DS . 'loyaltylion-client' . DS . 'main.php' );

    $options = array();

    if (isset($_SERVER['LOYALTYLION_API_BASE'])) {
      $options['base_uri'] = $_SERVER['LOYALTYLION_API_BASE'];
    }

    $this->client = new LoyaltyLion_Client($this->token, $this->secret, $options);

    $this->session = Mage::getSingleton('core/session');
  }

  private function isEnabled() {
    if (empty($this->token) || empty($this->secret)) return false;
    return true;
  }

  public function handleOrderCreate(Varien_Event_Observer $observer) {
    if (!$this->isEnabled()) return;

    $order = $observer->getEvent()->getOrder();

    $data = array(
      'merchant_id' => $order->getId(),
      'customer_id' => $order->getCustomerId(),
      'customer_email' => $order->getCustomerEmail(),
      'total' => (string) $order->getBaseGrandTotal(),
      'total_shipping' => (string) $order->getBaseShippingAmount(),
      'number' => (string) $order->getIncrementId(),
    );

    if ($order->getBaseTotalDue() == $order->getBaseGrandTotal()) {
      $data['payment_status'] = 'not_paid';
    } else if ($order->getBaseTotalDue() == 0) {
      $data['payment_status'] = 'paid';
    } else {
      $data['payment_status'] = 'partially_paid';
      $data['total_paid'] = $order->getBaseTotalPaid();
    }

    if ($this->session->getLoyaltyLionReferralId())
      $data['referral_id'] = $this->session->getLoyaltyLionReferralId();

    $response = $this->client->orders->create($data);

    if ($response->success) {
      Mage::log('[LoyaltyLion] Tracked order OK');
    } else {
      Mage::log('[LoyaltyLion] Failed to track order - status: ' . $response->status . ', error: ' . $response->error,
        Zend_Log::ERR);
    }
  }

  public function handleOrderUpdate(Varien_Event_Observer $observer) {
    if (!$this->isEnabled()) return;

    $this->sendOrderUpdate($observer->getEvent()->getOrder());
  }

  public function handleCustomerRegistration(Varien_Event_Observer $observer) {
    if (!$this->isEnabled()) return;

    $customer = $observer->getEvent()->getCustomer();

    $this->trackSignup($customer);
  }

  public function handleCustomerRegistrationOnepage(Varien_Event_Observer $observer) {
    if (!$this->isEnabled()) return;

    $customer = $observer->getEvent()->getSource();

    // this event is fired at multiple times during checkout before the customer has actually been saved,
    // so we'll ignore most of those events
    if (!$customer->getId()) return;

    // this event is also fired all over the place, even after the customer has been created. alas, this is
    // the only reliable way to find out if a new account has been created during checkout, so...
    //
    // we'll check the created_at time of the customer. if it's more than a minute in the past we'll assume
    // this is not a new customer. in theory, this event should never fire more than a few seconds after a
    // NEW account has been created, so this check ought to do what we want...

    if ($customer->getCreatedAtTimestamp() < (time() - 60)) return;

    $this->trackSignup($customer);
  }

  /**
   * If a referral id is present (?ll_ref_id=xyz), save it to the session so it can be sent off with
   * tracked event calls later
   *
   * @param  Varien_Event_Observer $observer [description]
   * @return [type]                          [description]
   */
  public function saveReferralId(Varien_Event_Observer $observer) {
    if (!$this->isEnabled()) return;

    $referral_id = Mage::app()->getRequest()->getParam('ll_ref_id');
    if (!$referral_id) return;

    if ($this->session->getLoyaltyLionReferralId()) return; // don't set it again if it exists

    $this->session->setLoyaltyLionReferralId($referral_id);
  }

  /**
   * Send an updated order to the Orders API
   *
   * Because the update endpoint is idempotent, this can be called as many times as needed to catch all
   * updates to an order without worrying about missing any order updates, as we send it all off here
   *
   * @param  [type] $order [description]
   * @return [type]        [description]
   */
  private function sendOrderUpdate($order) {
    if (!$order || !$order->getId()) return;

    $data = array(
      'refund_status' => 'not_refunded',
      'total_refunded' => 0,
    );

    if ($order->getBaseTotalDue() == $order->getBaseGrandTotal()) {
      $data['payment_status'] = 'not_paid';
      $data['total_paid'] = 0;
    } else if ($order->getBaseTotalDue() == 0) {
      $data['payment_status'] = 'paid';
      $data['total_paid'] = $order->getBaseGrandTotal();
    } else {
      $data['payment_status'] = 'partially_paid';
      $data['total_paid'] = $order->getBaseTotalPaid();
    }

    $data['cancellation_status'] = $order->getState() == 'canceled' ? 'cancelled' : 'not_cancelled';

    $total_refunded = $order->getBaseTotalRefunded();

    if ($total_refunded > 0) {
      if ($total_refunded < $order->getBaseGrandTotal()) {
        $data['refund_status'] = 'partially_refunded';
        $data['total_refunded'] = $total_refunded;
      } else {
        // assume full refund. this should be fine as magento appears to only allow refunding up to
        // the amount paid
        $data['refund_status'] = 'refunded';
        $data['total_refunded'] = $order->getBaseGrandTotal();
      }
    }

    $response = $this->client->orders->update($order->getId(), $data);

    if ($response->success) {
      Mage::log('[LoyaltyLion] Updated order OK');
    } else if ($response->status != 404) {
      // sometimes this will get fired before the order has been created, so we'll get a 404 back - no reason to
      // error, because this is expected behaviour
      Mage::log('[LoyaltyLion] Failed to update order - status: ' . $response->status . ', error: ' . $response->error,
        Zend_Log::ERR);
    }
  }

  /**
   * Track a signup event for the given customer
   *
   * @param  [type] $customer [description]
   * @return [type]           [description]
   */
  private function trackSignup($customer) {

    $data = array(
      'customer_id' => $customer->getId(),
      'customer_email' => $customer->getEmail(),
      'date' => date('c'),
    );

    if ($this->session->getLoyaltyLionReferralId())
      $data['referral_id'] = $this->session->getLoyaltyLionReferralId();

    $response = $this->client->events->track('signup', $data);

    if ($response->success) {
      Mage::log('[LoyaltyLion] Tracked event [signup] OK');
    } else {
      Mage::log('[LoyaltyLion] Failed to track event - status: ' . $response->status . ', error: ' . $response->error,
        Zend_Log::ERR);
    }
  }
}
