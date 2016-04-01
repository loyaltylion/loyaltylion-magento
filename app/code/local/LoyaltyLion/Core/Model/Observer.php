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

  private function getItems($orderId) {
    $collection = Mage::getResourceModel('sales/order_item_collection');
    $collection->addAttributeToFilter('order_id', array($orderId));
    $items = array();
    foreach ($collection->getItems() as $item) {
      $items[] = $item->toArray();
    }
    return $items;
  }

  private function getAddresses($orderId) {
    $addresses = array();
    $collection = Mage::getResourceModel('sales/order_address_collection');
    $collection->addAttributeToFilter('parent_id', array($orderId));
    foreach ($collection->getItems() as $item) {
      $addresses[] = $item->toArray();
    }
    return $addresses;
  }

  private function getComments($orderId) {
    $comments = array();
    $collection = Mage::getResourceModel('sales/order_status_history_collection');
    $collection->setOrderFilter(array($orderId));
    foreach ($collection->getItems() as $item) {
      $comments[] = $item->toArray();
    }
    return $comments;

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
      'guest' => (bool) $order->getCustomerIsGuest(),
      'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
      'user_agent' => $_SERVER['HTTP_USER_AGENT'],
      '$magento_payload' => $order->toArray()
    );

    $data['$magento_payload']['order_items'] = $this->getItems($order->getId());
    $data['$magento_payload']['order_comments'] = $this->getComments($order->getId());
    $data['$magento_payload']['addresses'] = $this->getAddresses($order->getId());
    $data['$magento_version'] = Mage::getVersion();
    $data['$magento_platform'] = Mage::getEdition();
    $data['$magento_module_version'] = (string) Mage::getConfig()->getModuleConfig("LoyaltyLion_Core")->version;

    if ($order->getBaseTotalDue() == $order->getBaseGrandTotal()) {
      $data['payment_status'] = 'not_paid';
    } else if ($order->getBaseTotalDue() == 0) {
      $data['payment_status'] = 'paid';
    } else {
      $data['payment_status'] = 'partially_paid';
      $data['total_paid'] = $order->getBaseTotalPaid();
    }

    if ($order->getCouponCode()) {
      $data['discount_codes'] = array(
        array(
          'code' => $order->getCouponCode(),
          'amount' => abs($order->getDiscountAmount()),
        ),
      );
    }

    if ($this->session->getLoyaltyLionReferralId())
      $data['referral_id'] = $this->session->getLoyaltyLionReferralId();

    $tracking_id = $this->getTrackingIdFromSession();

    if ($tracking_id)
      $data['tracking_id'] = $tracking_id;

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
   * This will also check for an `ll_eid` parameter (a tracking id) and save that, if it exists
   *
   * @param  Varien_Event_Observer $observer [description]
   * @return [type]                          [description]
   */
  public function saveReferralAndTrackingId(Varien_Event_Observer $observer) {
    if (!$this->isEnabled()) return;

    $referral_id = Mage::app()->getRequest()->getParam('ll_ref_id');

    // don't overwrite an existing referral_id in the session, if one exists
    if ($referral_id && !$this->session->getLoyaltyLionReferralId()) {
      $this->session->setLoyaltyLionReferralId($referral_id);
    }

    // check and set tracking_id by ll_eid param

    $tracking_id = Mage::app()->getRequest()->getParam('ll_eid');
    if (!$tracking_id) return;

    // I can't determine the expiration mechanics behind `$this->session`, so we'll do the same thing
    // we did for PrestaShop and attach a timestamp to the tracking_id, so we can ignore it if it's
    // too old when we track an event later

    $value = time() . ':::' . $tracking_id;
    $this->session->setLoyaltyLionTrackingId($value);
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
      'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
      'user_agent' => $_SERVER['HTTP_USER_AGENT']
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

    $data['$magento_payload'] = $order->toArray();
    $data['$magento_payload']['order_items'] = $this->getItems($order->getId());
    $data['$magento_payload']['order_comments'] = $this->getComments($order->getId());
    $data['$magento_payload']['addresses'] = $this->getAddresses($order->getId());
    $data['$magento_version'] = Mage::getVersion();
    $data['$magento_platform'] = Mage::getEdition();
    $data['$magento_module_version'] = (string) Mage::getConfig()->getModuleConfig("LoyaltyLion_Core")->version;

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
      'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
      'user_agent' => $_SERVER['HTTP_USER_AGENT']
    );

    if ($this->session->getLoyaltyLionReferralId())
      $data['referral_id'] = $this->session->getLoyaltyLionReferralId();

    $tracking_id = $this->getTrackingIdFromSession();

    if ($tracking_id)
      $data['tracking_id'] = $tracking_id;

    $response = $this->client->events->track('$signup', $data);

    if ($response->success) {
      Mage::log('[LoyaltyLion] Tracked event [signup] OK');
    } else {
      Mage::log('[LoyaltyLion] Failed to track event - status: ' . $response->status . ', error: ' . $response->error,
        Zend_Log::ERR);
    }
  }

  /**
   * Check the session for a `tracking_id`, and return it unless it has expired
   *
   * @return [type] Tracking id or null if it doesn't exist or has expired
   */
  private function getTrackingIdFromSession() {
    if (!$this->session->getLoyaltyLionTrackingId())
      return null;

    $values = explode(':::', $this->session->getLoyaltyLionTrackingId());

    if (empty($values))
      return null;

    if (count($values) != 2)
      return $values[0];

    // for now, let's have a 24 hour expiration time on the timestamp
    if (time() - (int)$values[0] > 86400)
      return null;

    return $values[1];
  }
}
