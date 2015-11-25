<?php
/* LoyaltyLion Coupon Import API 
*
* @category LoyaltyLion
* @package LoyaltyLion_CouponImport
* @author Patrick Molgaard 
*/
class LoyaltyLion_CouponImport_Model_Api2_PriceRule_Rest_Admin_V1 extends LoyaltyLion_CouponImport_Model_Api2_PriceRule
{
    /**
     * Create shopping cart price rule
     *
     * @param array $coupons
     * @return string|void
     */
    protected function _create($priceRule)
    {
    }
    /**
     * Retrieve price rule
     *
     * @return array
     */
    protected function _retrieve()
    {
        $ruleId = $this->getRequest()->getParam('rule_id');
        $rule = $this->_loadSalesRule($ruleId);
	$data = $rule->toArray();
        return $data;
    }
    /**
     * Load sales rule by ID.
     *
     * @param int $ruleId
     * @return Mage_SalesRule_Model_Rule
     */
    protected function _loadSalesRule($ruleId)
    {
        if (!$ruleId) {
            $this->_critical(Mage::helper('salesrule')
                ->__('Rule ID not specified.'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        $rule = Mage::getModel('salesrule/rule')->load($ruleId);
        if (!$rule->getId()) {
            $this->_critical(Mage::helper('salesrule')
                ->__('Rule was not found.'), Mage_Api2_Model_Server::HTTP_NOT_FOUND);
        }
        return $rule;
    }
}
