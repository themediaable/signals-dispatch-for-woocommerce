=== Signals Dispatch for WooCommerce ===
Contributors: themediaable
Tags: woocommerce, whatsapp, notifications, order-notifications, business-api
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WooCommerce order notifications via WhatsApp Business Cloud API with automatic queueing, delivery logs, and webhook status updates.

== Description ==

Signals Dispatch for WooCommerce integrates your WooCommerce store with the WhatsApp Business Cloud API, enabling automated order notification messages to customers.

= Free Features =

* **Automated Order Notifications** – Send WhatsApp messages when order status changes (processing, completed, on-hold, cancelled)
* **Template-Based Messaging** – Use pre-approved WhatsApp message templates with dynamic order variables
* **Message Queue** – Reliable message delivery using WooCommerce Action Scheduler
* **Delivery Tracking** – Real-time message status updates via webhooks (sent, delivered, read, failed)
* **Comprehensive Logs** – Full message history with payload and response details
* **Checkout Opt-in** – WhatsApp consent checkbox on checkout (classic and block checkout)
* **Manual Send** – Send a template message from any WooCommerce order page
* **Consent Enforcement** – Honors opt-in/opt-out records, with built-in checkout opt-in capture

= Pro Features (Coming Soon) =

* **Auto Retries** – Failed messages are automatically retried via Action Scheduler
* **Bulk Messaging** – Send messages to multiple customers at once
* **Advanced Analytics** – Delivery rate dashboards and insights
* **Priority Support** – Dedicated support channel

= Requirements =

* WordPress 6.0 or higher
* WooCommerce 7.0 or higher (provides Action Scheduler)
* PHP 7.4 or higher
* WhatsApp Business Account with Cloud API access

= Third-Party Services =

This plugin connects to the Meta WhatsApp Business Cloud API to send messages. By using this plugin, you agree to Meta's terms of service:

* [WhatsApp Business Platform](https://business.whatsapp.com/)
* [Meta Terms of Service](https://www.facebook.com/legal/terms)
* [Meta Privacy Policy](https://www.facebook.com/privacy/policy/)

== Installation ==

1. Upload the `signals-dispatch-woocommerce` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Signals → Setup** to configure your WhatsApp Business API credentials

= Configuration =

For detailed instructions on obtaining your WhatsApp Business API credentials, see the [WhatsApp API Setup Guide](https://github.com/themediaable/signals-dispatch-woocommerce/blob/main/docs/whatsapp-api-setup.md).

**Step 1: API Credentials**

Navigate to Signals → Setup and enter your WhatsApp Business API credentials:

* Phone Number ID – Your WhatsApp Business phone number ID
* WABA ID – WhatsApp Business Account ID
* Access Token – Permanent or temporary access token from Meta
* Webhook Verify Token – Custom token for webhook verification

**Step 2: Webhook Setup**

Configure your WhatsApp Business App to send webhooks to:

`https://yoursite.com/wp-json/signals/v1/webhook`

Use the Verify Token you configured in Step 1.

**Step 3: Test Message**

Send a test message to verify your configuration is working correctly.

== Frequently Asked Questions ==

= What WhatsApp templates can I use? =

You can use any approved WhatsApp message templates from your WhatsApp Business Account. The plugin supports utility templates with dynamic variable substitution.

= How do I map order data to template variables? =

In Dispatch Rules, specify a JSON array of variable names that correspond to your template's placeholders:

`["billing_first_name", "order_number", "order_total"]`

Available variables include: order_id, order_number, order_total, order_currency, billing_first_name, billing_last_name, billing_phone, billing_email, shipping_first_name, shipping_last_name, status, site_name.

= Why aren't messages being sent? =

Check the following:
1. Verify your API credentials are correct in Signals → Setup
2. Ensure the customer has a valid phone number in their billing details
3. Check that dispatch rules are enabled for the order status
4. Verify that Action Scheduler is running (provided by WooCommerce)

= How do I receive delivery status updates? =

Configure your WhatsApp Business App to send webhooks to your site's endpoint. The plugin will automatically update message status as sent, delivered, read, or failed.

= Does the plugin support customer consent management? =

Yes. A "Send me order updates on WhatsApp" checkbox is displayed at checkout. When checked, a consent record is saved automatically. You can also enable consent enforcement in Settings to ensure messages are only sent to customers who opted in.

= Can I manually send a WhatsApp message from an order? =

Yes. A "Send WhatsApp Message" meta box appears on the WooCommerce order page. Choose a dispatch rule and click "Send Now" to send a template message immediately.

== Screenshots ==

1. Setup wizard with API credential configuration
2. Dispatch rules for mapping events to templates
3. Message logs with delivery status
4. System health check

== Changelog ==

= 1.0.0 =
* Added WhatsApp opt-in checkbox on checkout (classic and block checkout)
* Added manual send meta box on WooCommerce order page
* Added Upgrade page with free vs. pro feature comparison
* Added upgrade promotion card on Help page
* Updated consent FAQ to reflect built-in checkout opt-in
* Webhook delivery status tracking confirmed as free feature
* HPOS (High-Performance Order Storage) compatible

= 0.2.0 =
* Refactored to OOP architecture with proper abstraction and encapsulation
* Implemented Repository pattern for database operations
* Split admin into separate controllers following Single Responsibility Principle
* Added PSR-4 autoloading for all classes
* Full WordPress Coding Standards compliance

= 0.1.0 =
* Initial release
* WhatsApp Cloud API integration
* Order status change notifications
* Message queue with retry logic
* Webhook status updates
* Admin setup wizard

== Upgrade Notice ==

= 1.0.0 =
New: checkout opt-in, manual send from order page, and upgrade page. Safe to update.

= 0.2.0 =
Major refactoring with improved code architecture. Database structure unchanged – safe to update.

= 0.1.0 =
Initial release.
