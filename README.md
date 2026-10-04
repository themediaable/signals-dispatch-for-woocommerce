# Signals Dispatch for WooCommerce

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/themediaable/signals-dispatch-for-woocommerce)
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
- **Consent Enforcement**: Honors opt-in consent records with built-in checkout capture
- **HPOS Compatible**: Supports WooCommerce High-Performance Order Storage

## Requirements

- WordPress 6.0 or higher
- WooCommerce 7.0 or higher (provides Action Scheduler)
- PHP 7.4 or higher
- WhatsApp Business Account with Cloud API access

> **New to WhatsApp Business API?** See our [WhatsApp API Setup Guide](docs/whatsapp-api-setup.md) for step-by-step instructions on obtaining your credentials.

## Installation

1. Download the plugin from GitHub or install via Composer
2. Upload to `/wp-content/plugins/signals-dispatch-for-woocommerce/`
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
https://yoursite.com/wp-json/tmasignals/v1/webhook
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

# Run isolated regression tests (no database or API credentials needed)
composer test

# Check locked dependencies for known advisories
composer audit --locked
```

### GitHub Actions

The first-phase automation uses standard Ubuntu runners, read-only repository
permissions, and commit-pinned actions. No Meta credentials, WordPress.org
credentials, staging server, or Copilot subscription is needed to execute it.

| Workflow | Trigger | Checks |
| --- | --- | --- |
| [Plugin CI](.github/workflows/ci.yml) | Pull requests, pushes to `main`/`master`, manual | Composer validation, PHP syntax and regression tests on PHP 7.4/8.3, coding standards on PHP 7.4, packaged WordPress Plugin Check |
| [Dependency audit](.github/workflows/dependencies.yml) | Weekly on Monday, dependency changes, manual | Known advisories in all locked dependencies, including development tools |
| [Build release ZIP](.github/workflows/release.yml) | Manual only | Coding standards, regression tests, ZIP/version validation, packaged Plugin Check, downloadable ZIP |

[Dependabot](.github/dependabot.yml) checks Composer dependencies and GitHub
Actions weekly and groups update PRs to reduce noise. Updates are not
automatically merged. Scheduled workflows run from the default branch.

The PHPUnit suite executes the real controllers, queue service, and repositories
with minimal WordPress/WooCommerce boundary doubles and a mocked database/API.
It covers order/phone consent precedence, recipient validation, order event
scheduling, transient retries and their ceiling, webhook HMAC verification and
status updates, manual-send capabilities/nonces, and the existing 30-second
manual-send duplicate guard. It does not prove full checkout/browser integration,
database SQL correctness, scheduler availability, performance, or concurrent/
automatic dispatch idempotency. Those require the next integration-test phase.
The doubles live only in `tests/` and never ship in the plugin ZIP.

#### Enabling and demonstrating the workflows

1. Commit and push these files to the plugin repository, with Actions enabled.
2. Open a pull request to see `PHP 7.4`, `PHP 8.3`, and
   `Packaged WordPress Plugin Check` results. Dependency changes also run the audit.
3. For a demo, change an expected value in a regression test on a temporary
   branch, open a PR, inspect the failed job, then restore the assertion and
   observe the next successful run. Do not merge the deliberately failing change.
4. On supported repository plans, configure branch protection/rulesets to require
   these checks. Merely adding workflow files does not block merging.

#### Building a release

In GitHub, select **Actions > Build release ZIP > Run workflow**, select the
committed branch to build, and download the ZIP from the successful run's
artifacts. It is retained for seven days. This does not publish a GitHub release,
deploy to production, or upload to WordPress.org.

For a local build from committed files:

```bash
composer build:zip
bash bin/check-release.sh
```

The build exports `HEAD`, not uncommitted source changes. It excludes CI files,
tests, and development dependencies, while retaining the production autoloader.
The validator requires the plugin header, `TMASD_VERSION`, and readme stable tag
to match and rejects incomplete or development-contaminated packages. The build
replaces an existing ZIP with the same version filename.

#### Keeping costs low

Standard runner execution is free for public repositories. GitHub Free private
repositories share the owner's allowance of 2,000 runner minutes/month and
500 MB artifact storage; other repositories also consume that allowance.
Parallel job durations add together. This small matrix deliberately does not
certify every PHP/WordPress/WooCommerce combination.

Superseded CI/audit runs are cancelled, coverage is disabled, and only manual
release runs retain release ZIPs. Plugin Check also uploads its small report
using the action's default retention; configure repository artifact retention
to seven days in **Settings > Actions > General** if desired. Configure
available Actions spending limits/budgets in the owner's billing settings and
monitor usage; these workflows cannot enforce an account-wide billing cap.
There are no live WhatsApp sends in the regression suite.

See [GitHub Actions billing](https://docs.github.com/en/billing/concepts/product-billing/github-actions)
for current allowances and paid usage conditions.

### Project Structure

```text
signals-dispatch-for-woocommerce/
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
└── signals-dispatch-for-woocommerce.php
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
- Added WhatsApp opt-in consent checkbox on checkout (classic and block checkout)
- Added manual send meta box on WooCommerce order page (HPOS compatible)
- Added Coming Soon page showcasing planned Pro features with email enquiry
- Added upgrade promotion card on Help page
- Added Refresh Status button on Logs page for on-demand delivery status check
- Added trigger source column to logs (automatic vs manual)
- Added GDPR personal data export and erasure with guest customer support
- Renamed REST API namespace to `tmasignals/v1` for uniqueness
- Differentiated status badge colours (sent=blue, delivered=green, read=purple)
- Hardened AJAX nonce validation and admin JavaScript against XSS
- Webhook delivery status tracking confirmed as free feature
- Retry logic reserved for Pro (disabled in free tier)
- Optimised PSR-4 autoloading to single root namespace mapping

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
