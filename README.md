# Signals Dispatch for WooCommerce

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/themediaable/signals-dispatch-woocommerce)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://php.net/)
[![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D6.0-21759B.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Send WooCommerce order notifications via WhatsApp Business Cloud API using templated utility messages with automatic queueing, delivery logs, and webhook status updates.

## Description

Signals Dispatch for WooCommerce integrates your WooCommerce store with the WhatsApp Business Cloud API, enabling automated order notification messages to customers. The plugin supports:

- **Automated Order Notifications**: Send WhatsApp messages when order status changes (processing, completed, on-hold, cancelled)
- **Template-Based Messaging**: Use pre-approved WhatsApp message templates with dynamic order variables
- **Message Queue**: Reliable message delivery using WooCommerce Action Scheduler
- **Delivery Tracking**: Real-time message status updates via webhooks (sent, delivered, read, failed)
- **Comprehensive Logs**: Full message history with payload and response details
- **Checkout Opt-in**: WhatsApp consent checkbox on both classic and block checkout
- **Manual Send**: Send a template message directly from any WooCommerce order page
- **Consent Enforcement**: Honors opt-in/opt-out records with built-in checkout capture
- **HPOS Compatible**: Supports WooCommerce High-Performance Order Storage

## Requirements

- WordPress 6.0 or higher
- WooCommerce 7.0 or higher (provides Action Scheduler)
- PHP 7.4 or higher
- WhatsApp Business Account with Cloud API access

> **New to WhatsApp Business API?** See our [WhatsApp API Setup Guide](docs/whatsapp-api-setup.md) for step-by-step instructions on obtaining your credentials.

## Installation

1. Download the plugin from GitHub or install via Composer
2. Upload to `/wp-content/plugins/signals-dispatch-woocommerce/`
3. Run `composer install` in the plugin directory
4. Activate the plugin through the WordPress admin

## Configuration

For detailed instructions on obtaining these credentials, see the [WhatsApp API Setup Guide](docs/whatsapp-api-setup.md).

### Step 1: API Credentials

Navigate to **Signals → Setup** and enter your WhatsApp Business API credentials:

| Field | Description |
| ----- | ----------- |
| Phone Number ID | Your WhatsApp Business phone number ID |
| WABA ID | WhatsApp Business Account ID |
| Access Token | Permanent or temporary access token from Meta |
| Webhook Verify Token | Custom token for webhook verification |

### Step 2: Webhook Setup

Configure your WhatsApp Business App to send webhooks to:

```text
https://yoursite.com/wp-json/signals/v1/webhook
```

Use the Verify Token you configured in Step 1.

### Step 3: Test Message

Send a test message to verify your configuration is working correctly.

## Dispatch Rules

Create dispatch rules to map WooCommerce order events to WhatsApp templates.

### Available Events

| Event Key | Trigger |
| --------- | ------- |
| `order_status_processing` | Order status changed to Processing |
| `order_status_completed` | Order status changed to Completed |
| `order_status_on_hold` | Order status changed to On Hold |
| `order_status_cancelled` | Order status changed to Cancelled |

### Template Variables

Map template placeholders to order data using a JSON array:

```json
["billing_first_name", "order_number", "order_total"]
```

#### Available Variables

| Variable | Description |
| -------- | ----------- |
| `order_id` | Internal order ID |
| `order_number` | Display order number |
| `order_total` | Order total amount |
| `order_currency` | Currency code |
| `billing_first_name` | Customer first name |
| `billing_last_name` | Customer last name |
| `billing_phone` | Customer phone number |
| `billing_email` | Customer email |
| `shipping_first_name` | Shipping first name |
| `shipping_last_name` | Shipping last name |
| `status` | Current order status |
| `site_name` | WordPress site name |

## Checkout Opt-in

A "Send me order updates on WhatsApp" checkbox is displayed before the Place Order button on both classic and block checkout. When checked, a consent record is saved automatically. Enable "Require Consent" in Settings to ensure messages only go to customers who opted in.

## Manual Send

A "Send WhatsApp Message" meta box appears on the WooCommerce order edit screen (supports both legacy and HPOS order pages). Select a dispatch rule from the dropdown and click "Send Now" to send a template message immediately. A WooCommerce order note is added recording the send.

## Development

### Local Development

```bash
# Install dependencies
composer install

# Run coding standards check
composer phpcs

# Auto-fix coding standards
composer phpcbf
```

### Project Structure

```text
signals-dispatch-woocommerce/
├── src/
│   ├── Admin/          # Admin UI controllers
│   ├── API/            # REST API endpoints
│   ├── Checkout/       # Checkout opt-in (classic + block)
│   ├── Contracts/      # Interfaces
│   ├── Core/           # Bootstrap and DI container
│   ├── Database/       # Repository pattern classes
│   ├── Queue/          # Message queue service
│   └── Services/       # Business logic services
├── assets/             # CSS/JS assets
├── composer.json
├── phpcs.xml.dist
└── signals-dispatch-woocommerce.php
```

### PSR-4 Autoloading

The plugin uses a single root namespace mapping in `composer.json`:

```json
{
  "TMASD\\Signals\\Dispatch\\": "src/"
}
```

All sub-namespaces are resolved automatically from the directory structure — no per-namespace entries needed.

## Troubleshooting

| Issue | Solution |
| ----- | -------- |
| Messages not sending | Verify API credentials, ensure Action Scheduler is available |
| Webhook updates missing | Check verify token matches, confirm endpoint is accessible |
| Logs empty | Enable dispatch rules for the order status, verify billing phone exists |
| Checkout checkbox not showing | Ensure WooCommerce is active and checkout page is rendering correctly |
| Manual send button missing | Check that at least one dispatch rule is enabled |

## Changelog

### 1.0.0
- Added WhatsApp opt-in checkbox on checkout (classic and block checkout)
- Added manual send meta box on WooCommerce order page (HPOS compatible)
- Added Upgrade page with free vs. pro feature comparison
- Added upgrade promotion card on Help page
- Updated consent FAQ to reflect built-in checkout opt-in
- Webhook delivery status tracking confirmed as free feature
- Optimised PSR-4 autoloading to single root namespace mapping

### 0.2.0
- Refactored to OOP architecture with proper abstraction and encapsulation
- Implemented Repository pattern for database operations
- Split admin into separate controllers following Single Responsibility Principle
- Added PSR-4 autoloading for all classes
- Full WordPress Coding Standards compliance

### 0.1.0
- Initial release
- WhatsApp Cloud API integration
- Order status change notifications
- Message queue with retry logic
- Webhook status updates
- Admin setup wizard

## License

This plugin is licensed under the GPL v2 or later.

```text
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## Credits

Developed by [TheMediaAble](https://themediaable.com)
