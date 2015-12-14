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
        $model = Mage::getModel('salesrule/rule');
        $model->loadPost($priceRule);
        $model->setUseAutoGeneration($priceRule["use_auto_generation"]);
        $model->save();
        $data = $model->getData();
        $id = $data['rule_id'];
        return "/api/rest/loyaltylion/rules/{$id}";
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
    protected function _retrieveCollection()
    {
        $collection = Mage::getModel('salesrule/rule')
            ->getResourceCollection();
        $data = $collection->load()->toArray();
        return $data['items'];
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
