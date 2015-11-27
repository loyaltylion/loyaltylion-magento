<?php
class LoyaltyLion_CouponImport_Block_Adminhtml_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /*
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('loyaltylion/system/config/button.phtml');
    }
 
    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }
 
    /**
     * Return ajax url for button
     *
     * @return string
     */
    public function getAjaxSetupUrl()
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/quicksetup/setup');
    }
 
    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
            'id'        => 'loyaltylion_setup_button',
            'label'     => $this->helper('adminhtml')->__('Configure API access'),
            'onclick'   => 'javascript:doSetup(); return false;'
        ));
 
        return $button->toHtml();
    }
}
