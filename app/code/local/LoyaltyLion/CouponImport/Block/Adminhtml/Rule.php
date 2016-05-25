<?php

class LoyaltyLion_CouponImport_Block_Adminhtml_Rule extends Mage_Adminhtml_Block_Promo_Quote_Edit_Tab_Main
{
  protected function _prepareForm() {
    // In magento 1.6, we don't have REST API access, so we just add a bulk import option
    // to the pricerule UI. It's not pretty, but it will get the job done.
    parent::_prepareForm();
    $form = $this->getForm();
    // For other versions, we should hide this - no need to clutter the UI
    if (Mage::getVersion() < '1.7') {
      $fieldset = $form->addFieldset('loyaltylion', array('legend'=>Mage::helper('salesrule')->__('LoyaltyLion Vouchers')));
      $fieldset->addField('ll_codes', 'textarea', array(
          'name' => 'll_codes',
          'label' => Mage::helper('salesrule')->__('Voucher Codes'),
          'title' => Mage::helper('salesrule')->__('Voucher Codes'),
          'style' => 'width: 98%; height: 100px;',
      ));
      $this->setForm($form);
    }
    return $form;
  }
}
