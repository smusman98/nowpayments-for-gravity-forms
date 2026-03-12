=== NOWPayments for Gravity Forms ===
Contributors: coderpress
Tags: gravity-forms, payment, cryptocurrency, nowpayments, crypto
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept 300+ cryptocurrencies via NOWPayments on Gravity Forms. Simple payment and sandbox mode.

== Description ==

NOWPayments for Gravity Forms adds NOWPayments as a payment option in Gravity Forms. Simple payment only.

**Features:**

* Simple (one-time) payment
* Sandbox mode for testing
* Products and Services transaction type only (no subscription in this build)
* Webhook (IPN) handling with HMAC verification
* Redirect to NOWPayments checkout

== External services ==

This plugin uses the NOWPayments API to process cryptocurrency payments. NOWPayments is a crypto payment gateway that allows you to accept 300+ cryptocurrencies.

*   This plugin sends transaction data (amount, currency, order ID) to NOWPayments when a user initiates a checkout.
*   The checkout process happens on the NOWPayments hosted payment page (https://nowpayments.io).
*   The plugin receives payment status updates via NOWPayments IPN (Instant Payment Notification).
*   Service provided by: **NOWPayments Ltd.** ([Terms of Service](https://nowpayments.io/terms-of-service), [Privacy Policy](https://nowpayments.io/privacy-policy))

== Installation ==

1. Install and activate Gravity Forms.
2. Upload the plugin files to `/wp-content/plugins/nowpayments-for-gravity-forms`, or install via your update server.
3. Activate the plugin.
4. Go to Forms > Settings > NOWPayments and configure Live/Sandbox mode, API keys, and IPN secret.
5. Add a payment feed to your form: Form Settings > NOWPayments > Add New.

== Changelog ==

= 1.0.0 =
* Initial release
* Simple payment only (no subscription in this build)
* Sandbox mode
