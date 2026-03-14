# Signals Dispatch for WooCommerce - AI Development Instructions

## Project Overview

**Plugin Name:** Signals Dispatch for WooCommerce  
**Version:** 0.2.0  
**Namespace:** `TMASD\Signals\Dispatch`  
**Text Domain:** `signals-dispatch-woocommerce`  
**Repository:** `github.com-tma:themediaable/signals-dispatch-woocommerce`

### Purpose
WordPress/WooCommerce plugin that sends automated WhatsApp notifications via Meta's WhatsApp Business Cloud API when order statuses change. Features message queueing, delivery tracking via webhooks, and comprehensive logging.

---

## Technical Stack

| Component | Requirement |
|-----------|-------------|
| PHP | >= 7.4 |
| WordPress | >= 6.0 |
| WooCommerce | >= 7.0 (provides Action Scheduler) |
| Autoloading | Composer PSR-4 |
| Coding Standard | WordPress Coding Standards (WPCS) |

---

## Architecture & Design Patterns

### Directory Structure

```
signals-dispatch-woocommerce/
├── src/                          # PSR-4 autoloaded classes
│   ├── Admin/                    # Admin UI controllers
│   │   ├── AbstractAdminController.php
│   │   ├── AdminController.php   # Main admin, registers menu
│   │   ├── SetupController.php   # API credentials setup
│   │   ├── DispatchController.php # Template mapping rules
│   │   ├── LogsController.php    # Message logs viewer
│   │   └── HealthController.php  # System health checks
│   ├── API/                      # REST API endpoints
│   │   └── WebhookController.php # Handles Meta webhooks
│   ├── Contracts/                # Interfaces (abstractions)
│   │   ├── ServiceInterface.php
│   │   ├── RepositoryInterface.php
│   │   ├── PhoneNormalizerInterface.php
│   │   ├── ApiClientInterface.php
│   │   ├── TemplateMapperInterface.php
│   │   └── QueueInterface.php
│   ├── Core/                     # Bootstrap & DI
│   │   ├── AbstractService.php
│   │   └── Container.php         # Singleton DI container
│   ├── Database/                 # Repository pattern
│   │   ├── AbstractRepository.php
│   │   ├── LogRepository.php
│   │   ├── MappingRepository.php
│   │   ├── OptinRepository.php
│   │   └── SchemaManager.php     # DB table creation
│   ├── Queue/                    # Message queue
│   │   └── QueueService.php      # Action Scheduler integration
│   └── Services/                 # Business logic
│       ├── PhoneNormalizerService.php
│       ├── TemplateMapperService.php
│       └── ApiClientService.php
├── assets/                       # CSS/JS files
├── docs/                         # Documentation
│   └── whatsapp-api-setup.md
├── composer.json
├── phpcs.xml.dist
├── readme.txt                    # WordPress.org format
├── README.md                     # GitHub format
└── signals-dispatch-woocommerce.php  # Main plugin file
```

### Design Principles

1. **Single Responsibility Principle (SRP)**
   - Each class has one purpose
   - Admin pages split into separate controllers
   - Services handle specific business logic

2. **Abstraction & Encapsulation**
   - All services implement interfaces from `src/Contracts/`
   - Properties are `private` with getters/setters
   - Database access via Repository pattern

3. **Dependency Injection**
   - `Container` class manages service instantiation
   - Services receive dependencies via constructor

4. **Repository Pattern**
   - All database operations go through Repository classes
   - `AbstractRepository` provides common CRUD methods
   - Custom queries in specific repositories

---

## Coding Standards

### WordPress Coding Standards (WPCS)

```bash
# Check for violations
composer phpcs

# Auto-fix violations
composer phpcbf
```

**Configuration:** `phpcs.xml.dist`

### Key WPCS Rules

| Rule | Requirement |
|------|-------------|
| Nonce verification | Required for all form submissions |
| Capability checks | Use `current_user_can()` before admin actions |
| Data sanitization | `sanitize_text_field()`, `absint()`, etc. |
| Output escaping | `esc_html()`, `esc_attr()`, `wp_kses_post()` |
| Direct DB queries | Use `$wpdb->prepare()` for all queries |
| Translator comments | Required for all `__()` and `_e()` calls |

### PHPCS Ignore Patterns

When WPCS flags false positives (e.g., nonce verified in parent method):

```php
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent::process_form()
$value = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
```

For SQL with interpolation (table names):

```php
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe class property
$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ) );
```

---

## Namespace & Autoloading

### PSR-4 Configuration (composer.json)

```json
{
  "autoload": {
    "psr-4": {
      "TMASD\\Signals\\Dispatch\\": "src/",
      "TMASD\\Signals\\Dispatch\\Admin\\": "src/Admin/",
      "TMASD\\Signals\\Dispatch\\API\\": "src/API/",
      "TMASD\\Signals\\Dispatch\\Contracts\\": "src/Contracts/",
      "TMASD\\Signals\\Dispatch\\Core\\": "src/Core/",
      "TMASD\\Signals\\Dispatch\\Database\\": "src/Database/",
      "TMASD\\Signals\\Dispatch\\Queue\\": "src/Queue/",
      "TMASD\\Signals\\Dispatch\\Services\\": "src/Services/"
    }
  }
}
```

### After Adding/Moving Classes

```bash
composer dump-autoload
```

---

## Database

### Custom Tables

| Table | Purpose |
|-------|---------|
| `{prefix}tmasd_logs` | Message delivery logs |
| `{prefix}tmasd_mappings` | Event-to-template mappings |
| `{prefix}tmasd_optins` | Customer opt-in/opt-out status |

### Schema Management

- Tables created on plugin activation via `SchemaManager::create_tables()`
- Use `dbDelta()` for safe table creation/updates
- Always include `$wpdb->prefix` in table names

---

## REST API

### Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/signals/v1/webhook` | GET/POST | Meta webhook verification & callbacks |

### Webhook Security

- Verify token via header `X-TMASD-Verify-Token` or query param `verify_token`
- Validate webhook signatures from Meta

---

## Git Workflow

### Repository Setup

```bash
# SSH config uses github.com-tma alias for TheMediaAble account
git remote set-url origin git@github.com-tma:themediaable/signals-dispatch-woocommerce.git
```

### Commit Message Format (Conventional Commits)

```
<type>: <description>

[optional body]
```

**Types:**
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `refactor:` Code refactoring
- `style:` Formatting, WPCS fixes
- `test:` Adding tests
- `chore:` Maintenance tasks

**Examples:**
```
feat: Add customer opt-out functionality
fix: Resolve webhook signature validation error
docs: Add WhatsApp API setup guide
refactor: Split AdminController into page-specific controllers
style: Fix WPCS violations in repository classes
```

### .gitignore

```
/vendor/
.env
*.log
.DS_Store
.idea/
.vscode/
node_modules/
```

---

## Development Environment

### Local Setup (wp-env)

```bash
# Start WordPress
wp-env start

# Install dependencies
composer install

# Access admin
# URL: http://localhost:8888/wp-admin
# User: admin / password
```

### Plugin Activation Checklist

1. Activate plugin in WordPress admin
2. Navigate to Signals → Setup
3. Enter WhatsApp API credentials
4. Configure webhook in Meta Developer Console
5. Create dispatch rules
6. Test with a WooCommerce order

---

## File Header Template

### PHP Class Files

```php
<?php
/**
 * Class description.
 *
 * @package TMASD\Signals\Dispatch
 * @since   0.1.0
 */

declare( strict_types = 1 );

namespace TMASD\Signals\Dispatch\SubNamespace;

use TMASD\Signals\Dispatch\Contracts\SomeInterface;

/**
 * Class ClassName
 *
 * Detailed description of what this class does.
 *
 * @since 0.1.0
 */
class ClassName implements SomeInterface {
    // ...
}
```

### Main Plugin File Header

```php
<?php
/**
 * Plugin Name:       Signals Dispatch for WooCommerce
 * Plugin URI:        https://github.com/themediaable/signals-dispatch-woocommerce
 * Description:       Send WooCommerce order notifications via WhatsApp Business Cloud API.
 * Version:           0.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            TheMediaAble
 * Author URI:        https://themediaable.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       signals-dispatch-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * @package TMASD\Signals\Dispatch
 */
```

---

## Common Patterns

### Adding a New Service

1. Create interface in `src/Contracts/NewServiceInterface.php`
2. Create implementation in `src/Services/NewService.php`
3. Register in `Container::register_services()`
4. Access via `Container::get_instance()->get_service( 'new_service' )`

### Adding a New Admin Page

1. Create controller in `src/Admin/NewPageController.php` extending `AbstractAdminController`
2. Implement `render()` method for page output
3. Register submenu in `AdminController::register_admin_menu()`

### Adding a New Repository

1. Create class in `src/Database/NewRepository.php` extending `AbstractRepository`
2. Define `$table_name` property
3. Add any custom query methods

---

## Security Checklist

- [ ] Nonce verification on all form submissions
- [ ] Capability checks (`manage_woocommerce` for admin pages)
- [ ] Sanitize all input data
- [ ] Escape all output
- [ ] Use prepared statements for database queries
- [ ] Validate webhook signatures
- [ ] Store API tokens securely (WordPress options, not in code)

---

## Testing

### Manual Testing Checklist

1. **Plugin Activation**
   - Tables created successfully
   - No PHP errors

2. **Setup Page**
   - Credentials save correctly
   - Test message sends

3. **Dispatch Rules**
   - Rules save to database
   - Order status changes trigger queue

4. **Queue Processing**
   - Messages sent via Action Scheduler
   - Retry on failure

5. **Webhooks**
   - Verification works
   - Status updates logged

6. **Logs**
   - All messages appear
   - Correct status displayed

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Autoload not finding classes | Run `composer dump-autoload` |
| PHPCS errors | Run `composer phpcbf` for auto-fix |
| Git push permission denied | Check SSH config uses `github.com-tma` host |
| Messages not sending | Verify Action Scheduler is running |
| Webhook failing | Check verify token matches in both places |

---

## Version History

| Version | Changes |
|---------|---------|
| 0.2.0 | OOP refactoring, Repository pattern, WPCS compliance |
| 0.1.0 | Initial release |

---

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WooCommerce Developer Docs](https://developer.woocommerce.com/)
- [WhatsApp Business Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [Action Scheduler](https://actionscheduler.org/)
