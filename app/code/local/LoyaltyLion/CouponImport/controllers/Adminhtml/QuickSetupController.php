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
	$model = Mage::getResourceModel('api2/acl_global_role');
	$model->saveAdminToRoleRelation($userId, $roleId);
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
	$accessToken = $this->getAccessToken($consumer_id);

        return array_merge(array('consumer_key' => $data['key'], 'consumer_secret' => $data['secret'], 'id' => $consumer_id), $accessToken);
    }

    public function getAccessToken($consumer_id) {
	$requestToken = Mage::getModel('oauth/token')->createRequestToken($consumer_id, "https://loyaltylion.com/");
	$accessToken = $requestToken->convertToAccess();
	$accessData = $accessToken->getData();
	return array('token' => $accessData['token'], 'secret' => $accessData['secret']);
    }

    public function getOAuthCredentials($id) {
        Mage::log("LoyaltyLion: Retrieving OAuth credentials");
        $model = Mage::getModel('oauth/consumer');
        $model->load($id);
        $oauth = $model->getData();
	$accessToken = $this->getAccessToken($id);
        return array_merge(array('oauth_key' => $oauth['key'], 'oauth_secret' => $oauth['secret']), $accessToken);
    }

    public function submitOAuthCredentials($credentials) {
        Mage::log("LoyaltyLion: Submitting OAuth credentials to LoyaltyLion site");

        $token = Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_token');
        $secret = Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_secret');
        $setup_uri = 'loyaltylion.dev/magento/oauth_credentials';
        $admin_oauth_authorize = Mage::helper('adminhtml')->getUrl('adminhtml/oauth_authorize');
        $options = array(
            CURLOPT_URL => $setup_uri,
            CURLOPT_USERAGENT => 'loyaltylion-php-client-v2.0.0',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERPWD => $token . ':' . $secret,
            CURLOPT_POST =>  true,
        );
        $credentials['admin_base_url'] = $admin_oauth_authorize;
        $body = json_encode($credentials);
        $options += array(
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ),
        );
        $curl = curl_init();
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $headers = curl_getinfo($curl);
        $error_code = curl_errno($curl);
        $error_msg = curl_error($curl);
        if ($error_code != 0) {
            return $error_msg;
        }
        if ($headers['http_code'] == 200) {
            return "ok";
        } elseif (($headers['http_code']) >= 400 && ($headers['http_code'] < 500)) {
            return "client-error";
        }
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
