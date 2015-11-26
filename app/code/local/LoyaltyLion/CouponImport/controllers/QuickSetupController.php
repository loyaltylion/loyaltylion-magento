<?php

class LoyaltyLion_CouponImport_QuickSetupController extends Mage_Adminhtml_Controller_Action
{
    public function generateRestRole($name) {
        //check "rest role created" flag
        //if not set, create the role w/ all resources & return ID
        $role = Mage::getModel('api2/acl_global_role');
        $role->setRoleName($name)->save();
        $roleId = $role->getId();

        $rule = Mage::getModel('api2/acl_global_rule');
        if ($roleId) {
            //Purge existing rules for this role
            $collection = $rule->getCollection();
            $collection->addFilterByRoleId($role->getId());

            /** @var $model Mage_Api2_Model_Acl_Global_Rule */
            foreach ($collection as $model) {
                $model->delete();
            }
        }
        $ruleTree = Mage::getSingleton(
            'api2/acl_global_rule_tree',
            array('type' => Mage_Api2_Model_Acl_Global_Rule_Tree::TYPE_PRIVILEGE)
        );
        //Allow everything
        $resources = array(
            Mage_Api2_Model_Acl_Global_Rule::RESOURCE_ALL => array(
                null => Mage_Api2_Model_Acl_Global_Rule_Permission::TYPE_ALLOW
            )
        );

        $id = $role->getId();
        foreach ($resources as $resourceId => $privileges) {
            foreach ($privileges as $privilege => $allow) {
                if (!$allow) {
                    continue;
                }

                $rule->setId(null)
                    ->isObjectNew(true);

                $rule->setRoleId($id)
                    ->setResourceId($resourceId)
                    ->setPrivilege($privilege)
                    ->save();
            }
        }

        return $roleId
    }

    public function assignToRole($userId, $roleId) {
        $user = Mage::getModel("api/user")->load($userId);
        $user->setRoleId($roleId)->setUserId($userId);
        if ( $user->roleUserExists() === true ) {
            return false;
        } else {
            $user->add();
            return true;
        }
    }

    public function enableAllAttributes() {
        $type = 'admin';
        $ruleTree = Mage::getSingleton(
            'api2/acl_global_rule_tree',
            array('type' => Mage_Api2_Model_Acl_Global_Rule_Tree::TYPE_ATTRIBUTE)
        );
        /** @var $attribute Mage_Api2_Model_Acl_Filter_Attribute */
        $attribute = Mage::getModel('api2/acl_filter_attribute');
        /** @var $collection Mage_Api2_Model_Resource_Acl_Filter_Attribute_Collection */
        $collection = $attribute->getCollection();
        $collection->addFilterByUserType($type);
        /** @var $model Mage_Api2_Model_Acl_Filter_Attribute */
        foreach ($collection as $model) {
            $model->delete();
        }
        $resources = array(
            Mage_Api2_Model_Acl_Global_Rule::RESOURCE_ALL => array(
                null => Mage_Api2_Model_Acl_Global_Rule_Permission::TYPE_ALLOW; 
        )
        );
        foreach ($resources as $resourceId => $operations) {
            if (Mage_Api2_Model_Acl_Global_Rule::RESOURCE_ALL === $resourceId) {
                $attribute->setUserType($type)
                    ->setResourceId($resourceId)
                    ->save();
            } else {
                foreach ($operations as $operation => $attributes) {
                    $attribute->setId(null)
                        ->isObjectNew(true);
                    $attribute->setUserType($type)
                        ->setResourceId($resourceId)
                        ->setOperation($operation)
                        ->setAllowedAttributes(implode(',', array_keys($attributes)))
                        ->save();
                }
            }
        }
    }

    public function generateOAuthCredentials($name) {
        $model = Mage::getModel('oauth/consumer');
        $helper = Mage::helper('oauth');
        $key = $helper->generateConsumerKey();
        $secret = $helper->generateConsumerSecret();
        $model->setKey($key);
        $model->setSecret($secret);
        $model->setName($name);
        $model->save();
        return array('key' => $key, 'secret' => $secret);
    }

    public function submitOAuthCredentials($credentials) {
        echo $credentials['key'] . ' ' . $credentials['secret'];
    }

    public function LLAPISetup() {
        $currentUser = Mage::getSingleton('admin/session')->getUser()->getId();
        $roleName = 'LoyaltyLion_TEST';
        $AppName = 'LoyaltyLion_Oauth_App';
        $roleID = $this->generateRestRole($roleName);
        $assigned = $this->assignToRole($currentUser, $roleID);
        $this->enableAllAttributes($roleID);
        $credentials = $this->generateOAuthCredentials($AppName);
        $this->submitOAuthCredentials($credentials);
    }
}	

$c = new LoyaltyLion_CouponImport_QuickSetupController();

$c->LLAPISetup();
