## LoyaltyLion for Magento

Version 0.0.1

### Compatibility

This module has been tested with Magento versions 1.7, 1.8 and 1.9.

### How to install

The easiest way to install is to use [modman](https://github.com/colinmollenhour/modman), but you can also just download and extract the module into your Magento folder if preferred.

Once installed, you can find the LoyaltyLion settings page under the `Customers` menu in the `Configuration` section of your Magento admin. From here you can enable the module and add your LoyaltyLion token and secret (you should have these already, if not - contact us)

### What it does

Once installed and correctly configured, this module will

1) Add the LoyaltyLion JavaScript SDK to your layout

2) Track customer signups and order creation/updates to the LoyaltyLion API

3) Listen for referrals and create referral cookies as needed, and send this information to LoyaltyLion

### What you need to do

You'll need to add the LoyaltyLion UI elements to your store before the program is fully active. Details of this can be found [in our documentation](https://loyaltylion.com/docs/ui-elements)
