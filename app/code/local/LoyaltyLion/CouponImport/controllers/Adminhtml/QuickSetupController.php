<?php

class LoyaltyLion_CouponImport_Adminhtml_QuickSetupController extends Mage_Adminhtml_Controller_Action
{
    public function generateRestRole($name) {
        Mage::log("LoyaltyLion: creating REST role");
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

        return $roleId;
    }

    public function assignToRole($userId, $roleId) {
        Mage::log("LoyaltyLion: Assigning current admin user to LoyaltyLion REST role");
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
        Mage::log("LoyaltyLion: Enabling attribute access for this REST role");
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
                null => Mage_Api2_Model_Acl_Global_Rule_Permission::TYPE_ALLOW 
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
        Mage::log("LoyaltyLion: Generating OAuth credentials");
        $model = Mage::getModel('oauth/consumer');
        $helper = Mage::helper('oauth');
        $data['key'] = $helper->generateConsumerKey();
        $data['secret'] = $helper->generateConsumerSecret();
        $model->addData($data);
        $model->setName($name);
        $model->save();
        $consumer_id = $model->getId();
        return array('key' => $data['key'], 'secret' => $data['secret'], 'id' => $consumer_id);
    }

    public function getOAuthCredentials($id) {
        Mage::log("LoyaltyLion: Retrieving OAuth credentials");
        $model = Mage::getModel('oauth/consumer');
        return $model->getData();
    }

    public function submitOAuthCredentials($credentials) {
        Mage::log("LoyaltyLion: Submitting OAuth credentials to LoyaltyLion API");
        return Mage::helper('adminhtml')->getUrl('adminhtml/oauth_authorize');
    }

    public function LLAPISetup() {
        Mage::log("LoyaltyLion: Setting up API access");
        $currentUser = Mage::getSingleton('admin/session')->getUser()->getId();
        $roleName = 'LoyaltyLion_TEST';
        $AppName = 'LoyaltyLion_Oauth_App';

        $roleID = Mage::getStoreConfig('loyaltylion/internals/rest_role_id');
        if (!$roleID) {
            $roleID = $this->generateRestRole($roleName);
            Mage::getModel('core/config')->saveConfig('loyaltylion/internals/rest_role_id', $roleID);
        } else {
            Mage::log("LoyaltyLion: Already created REST role, skipping");
        }

        //assigning just overwrites old permissions with "all", so doing it twice is harmless
        $assigned = $this->assignToRole($currentUser, $roleID);
        //As with the role assignment, we can do this twice and it's okay.
        $this->enableAllAttributes();

        $token = Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_token');
        $secret = Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_secret');
        if (empty($token) || empty($secret)) {
            Mage::log("LoyaltyLion: Could not generate OAuth credentials because token and/or secret not set.");
            return "not-configured-yet";
        }

        $OAuthConsumerID = Mage::getStoreConfig('loyaltylion/internals/oauth_consumer_id');
        if (!$OAuthConsumerID) {
            $credentials = $this->generateOAuthCredentials($AppName);
            $OAuthConsumerID = $credentials['id'];
            Mage::getModel('core/config')->saveConfig('loyaltylion/internals/oauth_consumer_id', $OAuthConsumerID);
            return $result;
        } else {
            Mage::log("LoyaltyLion: OAuth is already configured, skipping...");
            $credentials = $this->getOAuthCredentials($OAuthConsumerID);
        }

        $hasSubmitted = Mage::getStoreConfig('loyaltylion/internals/has_submitted_oauth');
        if ($hasSubmitted) {
            Mage::log("LoyaltyLion: OAuth is already submitted, skipping...");
            return "already-done";
        } 
        $result = $this->submitOAuthCredentials($credentials);

        Mage::getModel('core/config')->saveConfig('loyaltylion/internals/has_submitted_oauth', 1);
        return $result;
    }

    public function setupAction() {
        $result = $this->LLAPISetup();
        Mage::app()->getResponse()->setBody($result);
    }
}
