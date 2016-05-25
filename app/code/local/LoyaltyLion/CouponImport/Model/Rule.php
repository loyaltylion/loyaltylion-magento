<?php

class LoyaltyLion_CouponImport_Model_Rule extends Mage_SalesRule_Model_Rule
{
    protected $_unsavedCoupons;

    function loadPost(array $rule) {
        // At this point we can't persist the codes quite yet,
        // because the rule they belong to might not have been saved.
        // So we'll keep them until the _afterSave hook.
        if (isset($rule['ll_codes'])) {
            $codes = explode("\n", $rule['ll_codes']);
            foreach($codes as $i => $code) {
                $codes[$i] = trim($code);
            }
            if (count($codes)) {
                $this->_unsavedCoupons = $codes;
            }
        }
        return parent::loadPost($rule);
    }

    protected function _afterSave()
    {
        if (count($this->_unsavedCoupons)) {
            $ruleId = $this->getId();
            if ($ruleId) {
                $coupon = Mage::getModel('salesrule/coupon');
                $now = $this->getResource()->formatDate(
                    Mage::getSingleton('core/date')->gmtTimestamp()
                );
                $expirationDate = $this->toDate;
                foreach ($this->_unsavedCoupons as $code) {
                    $code = trim($code);
                    $coupon->setId(null)
                        ->setRuleId($ruleId)
                        ->setUsageLimit(1)
                        ->setUsagePerCustomer(1)
                        ->setExpirationDate($expirationDate)
                        ->setCreatedAt($now)
                        ->setCode($code)
                        ->save();
                }
            }
        }
        return parent::_afterSave();
    }
}
