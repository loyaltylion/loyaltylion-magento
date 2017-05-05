<?php

class LoyaltyLion_Core_Model_Placeholder extends Enterprise_PageCache_Model_Container_Abstract {

    public function applyWithoutApp(&$content)
    {
        return false;
    }

    protected function _getCacheId()
    {
        $id = 'll_holepunched_' . microtime() . '_' . rand(0,99);
        return $id;
    }

    protected function  _saveCache($data, $id, $tags = array(), $lifetime = null)
    {
        return $this;
    }

    /**
     * Render fresh block content.
     *
     * @return false|string
     */
    protected function _renderBlock()
    {
        $block = $this->_placeholder->getAttribute('block');
        $block = new $block;
        // Get the block_id attribute we originally set in our SDK block's
        // getCacheKeyInfo function.
        $block_id = $this->_placeholder->getAttribute('block_id');
        $block->setBlockId($block_id);
        $block->setLayout(Mage::app()->getLayout());
        return $block->toHtml();
    }
}
