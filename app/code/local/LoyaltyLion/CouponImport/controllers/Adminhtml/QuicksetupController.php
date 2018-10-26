<?php
require(
Mage::getModuleDir(
    '',
    'LoyaltyLion_Core'
) . DS . 'lib' . DS . 'loyaltylion-client' . DS . 'lib' . DS . 'connection.php'
);

class LoyaltyLion_CouponImport_Adminhtml_QuickSetupController extends Mage_Adminhtml_Controller_Action
{
    public $loyaltyLionURL = 'https://app.loyaltylion.com';
    public $appName = 'LoyaltyLion';

    public function setupAction()
    {
        $result = $this->LLAPISetup();
        Mage::app()->getResponse()->setBody($result);
    }

    private function LLAPISetup()
    {
        Mage::log("[LoyaltyLion] Setting up API access");
        $currentUser = Mage::getSingleton('admin/session')->getUser()->getId();

        $token = $this->getRequest()->getParam('token')
            ?: Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_token');
        $secret = $this->getRequest()->getParam('secret')
            ?: Mage::getStoreConfig('loyaltylion/configuration/loyaltylion_secret');

        if (empty($token) || empty($secret)) {
            Mage::log(
                "[LoyaltyLion] Could not generate OAuth credentials because token and/or secret not set."
            );
            return "not-configured-yet";
        }

        $scopeData = $this->resolveScope($this->getRequest()->getParam('code'));

        // the user maybe hasn't hit save on their config yet - so we do it for them.
        $this->saveConfig($token, $secret, $scopeData);

        // Set up rest roles, oauth consumers, etc.
        $credentials = $this->assertInternalConfig($currentUser);

        $result = $this->submitOAuthCredentials(
            $credentials,
            $token,
            $secret,
            $scopeData['website_id']
        );

        if ($result == "ok") {
            // Turn that configure button green :)
            Mage::getModel('core/config')->saveConfig(
                'loyaltylion/internals/has_submitted_oauth',
                1,
                $scopeData['scope'],
                $scopeData['scope_id']
            );
        }
        return $result;
    }

    private function resolveScope($code)
    {
        if (!empty($code)) {
            // having this means this config is scoped to a particular website,
            // rather than the root 'default' scope
            $websiteId = Mage::getModel('core/website')->load($code)->getId();
            $scope = 'websites';
            $scopeId = $websiteId;
        } else {
            // LL is being configured in the `default` scope.
            // We'll still report a guess at a websiteId; this could be wrong
            // but knowing the default website is probably better than nothing.
            $websiteId = $this->getFirstWebsite();
            $scope = 'default';
            $scopeId = 0;
        }

        return [
            'scope' => $scope,
            'scope_id' => $scopeId,
            'website_id' => $websiteId
        ];
    }

    private function saveConfig($token, $secret, $scopeData)
    {
        Mage::log("[LoyaltyLion] Saving new LoyaltyLion credentials");
        Mage::getModel('core/config')->saveConfig(
            'loyaltylion/configuration/loyaltylion_token',
            $token,
            $scopeData['scope'],
            $scopeData['scope_id']
        );
        Mage::getModel('core/config')->saveConfig(
            'loyaltylion/configuration/loyaltylion_secret',
            $secret,
            $scopeData['scope'],
            $scopeData['scope_id']
        );
    }

    private function assertInternalConfig($currentUser)
    {
        $this->assertRestRole($currentUser);
        return $this->assertOauthCredentials($currentUser);
    }

    private function assertRestRole($currentUser)
    {
        $roleID = $this->getRestRole() ?: $this->generateRestRole();

        // This is idempotent & safe to run repeatedly.
        $this->assignToRole($currentUser, $roleID);

        // assigning just overwrites old permissions with "all", so doing it twice is harmless
        $this->enableAllAttributes();
    }

    private function assertOauthCredentials($currentUser) {
        return $this->getOauthCredentials($currentUser) ?: $this->generateOauthCredentials($currentUser);
    }

    private function getRestRole()
    {
        Mage::log("[LoyaltyLion] Finding REST role");
        return  Mage::getModel('api2/acl_global_role')->load($this->appName, 'role_name')->getId();
    }

    private function generateRestRole()
    {
        Mage::log("[LoyaltyLion] creating REST role");
        $role = Mage::getModel('api2/acl_global_role');
        $role->setRoleName($this->appName)->save();
        $roleId = $role->getId();

        $rule = Mage::getModel('api2/acl_global_rule');
        if ($roleId) {
            // Purge existing rules for this role
            $collection = $rule->getCollection();
            $collection->addFilterByRoleId($role->getId());

            /** @var $model Mage_Api2_Model_Acl_Global_Rule */
            foreach ($collection as $model) {
                $model->delete();
            }
        }
        $ruleTree = Mage::getSingleton(
            'api2/acl_global_rule_tree',
            array(
                'type' => Mage_Api2_Model_Acl_Global_Rule_Tree::TYPE_PRIVILEGE
            )
        );
        // Allow everything
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

                $rule->setId(null)->isObjectNew(true);

                $rule->setRoleId($id)->setResourceId($resourceId)->setPrivilege(
                    $privilege
                )->
                    save(

                );
            }
        }

        return $roleId;
    }

    private function assignToRole($userId, $roleId)
    {
        Mage::log(
            "[LoyaltyLion] Assigning current admin user to LoyaltyLion REST role"
        );
        $model = Mage::getResourceModel('api2/acl_global_role');
        $model->saveAdminToRoleRelation($userId, $roleId);
    }

    private function enableAllAttributes()
    {
        Mage::log("[LoyaltyLion] Enabling attribute access for admin role");
        $type = 'admin';
        $ruleTree = Mage::getSingleton(
            'api2/acl_global_rule_tree',
            array(
                'type' => Mage_Api2_Model_Acl_Global_Rule_Tree::TYPE_ATTRIBUTE
            )
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
                $attribute
                    ->setUserType($type)
                    ->setResourceId($resourceId)
                    ->save();
            } else {
                foreach ($operations as $operation => $attributes) {
                    $attribute->setId(null)->isObjectNew(true);
                    $attribute
                        ->setUserType($type)
                        ->setResourceId($resourceId)
                        ->setOperation($operation)
                        ->setAllowedAttributes(implode(',', array_keys($attributes)))
                        ->save();
                }
            }
        }
    }

    private function generateOAuthCredentials($userID)
    {
        Mage::log("[LoyaltyLion] Generating OAuth credentials");
        $model = Mage::getModel('oauth/consumer');
        $helper = Mage::helper('oauth');
        $data['key'] = $helper->generateConsumerKey();
        $data['secret'] = $helper->generateConsumerSecret();
        $model->addData($data);
        $model->setName($this->appName);
        $model->save();
        $consumer_id = $model->getId();
        $accessToken = $this->getAccessToken($consumer_id, $userID);

        return array_merge(
            array(
                'consumer_key' => $data['key'],
                'consumer_secret' => $data['secret'],
                'id' => $consumer_id
            ),
            $accessToken
        );
    }

    private function getAccessToken($consumer_id, $userID)
    {
        $tokenData = $this->findAccessToken($consumer_id, $userID) ?: $this->createAccessToken($consumer_id, $userID);
        return array(
            'access_token' => $tokenData['token'],
            'access_secret' => $tokenData['secret']
        );
    }

    private function findAccessToken($consumer_id, $userID) {
        Mage::log("[LoyaltyLion] Finding existing OAuth token");
        $token = Mage::getModel('oauth/token')->load($consumer_id, 'consumer_id', $userID, 'admin_id');
        if ($token->getData()) {
            return $token->getData();
        }
        Mage::log("[LoyaltyLion] No existing OAuth token");
    }

    private function createAccessToken($consumer_id, $userID) {
        Mage::log("[LoyaltyLion] Generating OAuth token");
        $requestToken = Mage::getModel('oauth/token')->createRequestToken(
            $consumer_id,
            "https://loyaltylion.com/"
        );
        $requestToken->authorize($userID, 'admin');
        $accessToken = $requestToken->convertToAccess();
        return $accessToken->getData();
    }

    private function getOAuthCredentials($currentUser)
    {
        Mage::log("[LoyaltyLion] Retrieving OAuth credentials");
        $model = Mage::getModel('oauth/consumer')->load($this->appName, 'name');
	$oauth = $model->getData();
        if ($oauth) {
            $accessToken = $this->getAccessToken($model->getId(), $currentUser);
            return array_merge(
                array(
                    'consumer_key' => $oauth['key'],
                    'consumer_secret' => $oauth['secret']
                ),
                $accessToken
            );
        }
    }

    private function getCoreConfigVersions() {
        // It doesn't appear that the core_resource table is usable with Magento's
        // collection helpers, so we have to query directly.
        return Mage::getSingleton('core/resource')->
            getConnection('core_read')->
            fetchAll('SELECT * FROM core_resource');
    }

    private function submitOAuthCredentials(
        $credentials,
        $token,
        $secret,
        $websiteId
    ) {
        if (isset($_SERVER['LOYALTYLION_WEBSITE_BASE'])) {
            $this->loyaltyLionURL = $_SERVER['LOYALTYLION_WEBSITE_BASE'];
        }

        Mage::log(
            "[LoyaltyLion] Submitting OAuth credentials to LoyaltyLion site"
        );

        $connection = new LoyaltyLion_Connection(
            $token,
            $secret,
            $this->loyaltyLionURL
        );

        $setup_uri = '/magento/oauth_credentials';
        $credentials['base_url'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $credentials['extension_version'] = (string) Mage::getConfig()
            ->getModuleConfig("LoyaltyLion_Core")
            ->version;
	$credentials['module_config'] = $this->getCoreConfigVersions();
        if ($websiteId > 0) {
            $credentials['website_id'] = $websiteId;
        }

        $resp = $connection->post($setup_uri, $credentials);

        if (isset($resp->error)) {
            Mage::log(
                "[LoyaltyLion] Error submitting credentials: " . $resp->error
            );
            return "network-error";
        } elseif ((int) $resp->status >= 200 && (int) $resp->status <= 204) {
            return "ok";
        } elseif ((int) $resp->status == 422) {
            Mage::log(
                "[loyaltylion] error submitting credentials: " . $resp->status . ' ' . $resp->body
            );
            return "credentials-error";
        } else {
            Mage::log(
                "[loyaltylion] error submitting credentials: " . $resp->status . ' ' . $resp->body
            );
            return "unknown-error";
        }
    }

    private function getFirstWebsite()
    {
        $websites = Mage::getModel('core/website')->getCollection();
        foreach ($websites as $website) {
            $id = $website->getId();
            return $id;
        }
        return 0;
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/config');
    }
}
