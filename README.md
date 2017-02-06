## LoyaltyLion for Magento

Version 1.2.3

### Compatibility

This module has been tested with Magento versions 1.6, 1.7, 1.8 and 1.9.
Voucher import via the REST API is only supported on Magento >= 1.7.

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

### Changelog

* 1.2.3: Switch to new LoyaltyLion domain
* 1.2.2: Bugfix: handle unpaid orders on Magento 1.7 more reliably
* 1.2.1: Add multi-website support for oauth credential submission
* 1.2.0: Add support for importing voucher codes within your Magento Admin panel (Magento 1.6 only)
* 1.1.9: Partial compatibility with Magento 1.6
* 1.1.8: Update signup link
* 1.1.7: Use `$` namespace for core events
* 1.1.6: Fix controller/model case sensitivity
* 1.1.4: Fix default submission URL
* 1.1.3: Fix oauth credential submission
* 1.1.2: Eliminate "save config" as a separate button click
* 1.1.1: Send more information to LL Orders API
* 1.1.0: Extend the REST API with price rules and vouchers; submit API credentials to LoyaltyLion servers.
* 1.0.4: customer guest status is boolean
* 1.0.3: SUPEE-6788 compatibility
* 1.0.2: supports sending discounts used when tracking orders
* 1.0.1: supports sending loyaltylion `tracking_id` parameters
* 1.0.0: initial release
