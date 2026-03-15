# Signals by Mediaable — Project Specification (v1)

**Project codename:** Signals
**Brand (public):** Signals by Mediaable
**Company:** The Media Able
**Primary domain:** themediaablesignals.com
**Initial release (v1):** Signals **Dispatch** for WooCommerce (WhatsApp Cloud API)

> **Spec revision:** Updated 2026-03-15 to reflect v0.2.0 implementation state.
> Sections marked **[IMPLEMENTED]**, **[PARTIAL]**, or **[BACKLOG]** reflect current build status.

---

## 1) Executive summary

**Signals** is a platform-neutral product suite for **automations and customer notifications** across commerce platforms (WooCommerce first, Shopify later) and messaging channels (WhatsApp first, then email/SMS/push).

**v1 goal:** ship a reliable, secure, low-support WordPress plugin that sends WhatsApp **template messages** for WooCommerce order updates using **Bring-Your-Own WhatsApp Cloud API** credentials.

**Current status (v0.2.0):** Core engine, admin UI, webhook, queue, logging, consent enforcement, and WordPress privacy integration are all shipped and security-audited. The remaining gap before v1.0 is the checkout opt-in UX for consent collection (the enforcement side is done) and a clean WP.org release ZIP.

---

## 2) Product pillars

1. **Platform-neutral brand**
   - "Signals" stays relevant as you add Shopify and other connectors.

2. **Low-support by design**
   - Setup wizard, health checks, clear error messages, diagnostic export.

3. **Reliability**
   - Queue + retries, delivery status updates via webhook, logs for audits.

4. **Compliance-first**
   - Opt-in tracking, template messaging rules, safe defaults, WP privacy API.

5. **Incremental expansion**
   - Start with transactional/utility notifications, then add automations and additional channels.

---

## 3) Target users & ICP

### Primary ICP (v1)
- Small WooCommerce stores (India and globally) using WhatsApp for customer comms.
- COD-heavy stores needing confirmation to reduce fake COD orders.

### Secondary ICP
- WooCommerce agencies managing multiple client stores (multi-site licenses).

### Key problems solved
- Manual WhatsApp messaging is time-consuming and error-prone.
- Order status updates increase trust and reduce "Where is my order?" queries.
- COD confirmation reduces cancellations/refusals at delivery.

---

## 4) Product scope

### 4.1 v1 Module: Dispatch (WooCommerce → WhatsApp)
**Dispatch** automates transactional WhatsApp messages triggered by WooCommerce events.

#### v1 features (MVP)

**A) Setup & connection** [IMPLEMENTED]
- Tabbed setup page (Step 1: Credentials / Step 2: Webhook) that configures:
  - WhatsApp Cloud API: `phone_number_id`, `waba_id`, `access_token`
  - Webhook verify token and callback URL display
  - Opt-in requirement toggle (`tmasd_require_consent`)
- Required-field validation on save — shows per-field error listing which fields are missing.
- Each field has a description explaining what it is, plus a link to the Help page.
- Secrets (`access_token`, `app_secret`) are never echoed back into the form DOM; stored with `autoload=no`.

**B) Template mapping** [IMPLEMENTED]
- Dispatch Rules page: create/edit/delete event → template mappings.
- Maps WooCommerce events → WhatsApp template name + language (default `en_US`).
- Variable mapping: template placeholders (`{{1}}`, `{{2}}`, …) → resolver keys.
- `event_key` validated against known-events allowlist on save.
- `mapping_json` stripped to allowed resolver keys only on save (blocks arbitrary injection).

**C) Triggers** [IMPLEMENTED for order status; COD = BACKLOG]
- Order status events currently supported:
  - `order_status_processing`
  - `order_status_completed`
  - `order_status_on-hold`
  - `order_status_cancelled`
- COD confirmation trigger: **not yet implemented** (planned v1.1).

**D) Queue + retries** [IMPLEMENTED]
- Async sending via Action Scheduler (`as_enqueue_async_action`, group: `tmasd`).
- Max **2** retry attempts (`MAX_RETRY_ATTEMPTS = 2`), 10-second base delay.
- Retries are **fail-closed**: only retried on network errors (`WP_Error` / `http_code=0`), `429 Too Many Requests`, or `5xx` server errors. All other failures (4xx, invalid payload, etc.) are final.
- Idempotency: each order+event maps to at most one enabled rule; Action Scheduler prevents duplicate scheduling within a group.

**E) Webhook delivery status** [IMPLEMENTED]
- REST endpoint `GET/POST /wp-json/tmasd/v1/webhook` handles Meta callbacks.
- `verify_signature()` validates HMAC-SHA256 of raw body against `app_secret`; **fails closed** when secret is missing.
- Only processes payloads where `object === 'whatsapp_business_account'`.
- Updates log rows with status `sent`, `delivered`, `read`, or `failed`. Unknown statuses produce no update (no misleading fallback to `sent`).

**F) Logging** [IMPLEMENTED]
- Structured log records: `phone_e164`, `template_name`, `payload_json`, `response_json`, `status`, `wa_message_id`, `error_code`, `error_message`.
- `status` values: `queued`, `sent`, `delivered`, `read`, `failed`, `skipped`.
- `skipped` is written for consent-blocked or otherwise-invalid requests (no API call made).
- Admin Logs viewer page with tabular display.
- Secrets (access tokens) are never stored in log rows.

**G) Consent / opt-in** [PARTIAL]
- **Enforcement (done):** `QueueService` checks `OptinRepository::has_consent($phone)` before dispatching when `tmasd_require_consent` is enabled. Blocks the send and writes a `skipped` log row.
- **Storage layer (done):** `wp_tmasd_optins` table and `OptinRepository` are ready to record consent.
- **Capture UX (backlog):** The checkout opt-in checkbox that writes consent rows does not exist yet. Until it is built, enabling "Require Consent" will block all sends unless rows are inserted via code or migration.
- Default: consent requirement is **off** — stores with existing consent mechanisms can leave it off and manage consent externally.

**H) WordPress privacy compliance** [IMPLEMENTED]
- `PrivacyController` hooks into `wp_privacy_personal_data_exporters` and `wp_privacy_personal_data_erasers`.
- Exporter: collects log rows and consent records for a given user; phone numbers are masked.
- Eraser: sets `phone_e164 = 'ANONYMISED'` in logs, permanently deletes consent records.
- Suggested privacy policy content added via `wp_add_privacy_policy_content()`.

**I) Admin UI pages** [IMPLEMENTED]
- **Setup** (`tmasd-setup`) — credentials + webhook tabs.
- **Dispatch** (`tmasd-dispatch`) — template mapping CRUD.
- **Logs** (`tmasd-logs`) — message log viewer.
- **Health** (`tmasd-health`) — connectivity and configuration status checks.
- **Help** (`tmasd-help`) — FAQ / documentation / support.
- All pages require capability `manage_woocommerce`; nonces on every form.

**J) Uninstall** [IMPLEMENTED]
- `uninstall.php` guarded by `WP_UNINSTALL_PLUGIN`.
- Deletes all 6 plugin options from `wp_options`.
- Drops tables `wp_tmasd_logs`, `wp_tmasd_template_map`, `wp_tmasd_optins`.

#### v1 explicit non-goals
- Marketing campaigns / broadcasts
- Abandoned cart automation
- Two-way chat / shared inbox
- AI chatbot inside WhatsApp
- Shopify connector (planned for later)

---

## 5) Roadmap (high-level)

### v1.0 (current release candidate — remaining gaps)
- **Checkout opt-in checkbox** — collect a `wp_tmasd_optins` record at checkout ("Send me order updates on WhatsApp"). Requires: `woocommerce_checkout_fields` / `woocommerce_checkout_order_processed` hooks; record written with `consent_source=checkout`.
- **My Account opt-out toggle** — let customers change preference after checkout.
- Clean WP.org release ZIP (no `__MACOSX`, no dev vendor packages; `composer install --no-dev`).
- `Tested up to` updated after testing on latest WP.

### v1.1 (post-MVP hardening)
- COD confirmation flow (template + confirmation token endpoint).
- Abandoned cart reminder (utility template).
- "Manual send" button from order edit screen (admin action).
- Template packs + guided creation steps.
- Improved diagnostics bundle export.

### v1.2 (scale)
- Multi-number / multi-store support (Business tier).
- Segments + rules (simple IF/THEN).
- Email channel connector (optional).

### v2 (platform expansion)
- Shopify connector (order webhooks → Signals).
- Additional channels: SMS, email providers, push notifications.
- Optional SaaS dashboard (Laravel) for analytics + centralised settings.

---

## 6) Technical architecture (v1)

### 6.1 WordPress plugin (single-product build)
**Why:** fastest distribution, easiest adoption for WooCommerce users.

**Runtime requirements:**
- PHP 7.4+
- WordPress 6.0+
- WooCommerce 7.0+ (provides Action Scheduler)
- Plugin is self-contained — Composer vendor is bundled; no manual `composer install` required by end users.

**PHP namespace root:** `TMASD\\Signals\\Dispatch`

**Constants defined in main plugin file:**

| Constant | Value |
|---|---|
| `TMASD_VERSION` | `0.2.0` |
| `TMASD_OPTION_PHONE_NUMBER_ID` | `tmasd_phone_number_id` |
| `TMASD_OPTION_WABA_ID` | `tmasd_waba_id` |
| `TMASD_OPTION_ACCESS_TOKEN` | `tmasd_access_token` |
| `TMASD_OPTION_WEBHOOK_VERIFY_TOKEN` | `tmasd_webhook_verify_token` |
| `TMASD_OPTION_APP_SECRET` | `tmasd_app_secret` |
| `TMASD_OPTION_REQUIRE_CONSENT` | `tmasd_require_consent` |
| `TMASD_ACTION_SEND_TEMPLATE` | `tmasd_send_template_message` |
| `TMASD_CAPABILITY` | `manage_woocommerce` |

#### Source structure
```
src/
  Admin/
    AbstractAdminController.php
    AdminController.php          -- menu registration
    SetupController.php          -- credentials + webhook setup
    DispatchController.php       -- template mapping CRUD
    LogsController.php           -- log viewer
    HealthController.php         -- health checks
    HelpController.php           -- FAQ / docs
    PrivacyController.php        -- WP privacy export/erase hooks
  API/
    WebhookController.php        -- REST webhook endpoint
  Contracts/
    ApiClientInterface.php
    PhoneNormalizerInterface.php
    QueueInterface.php
    RepositoryInterface.php
    ServiceInterface.php
    TemplateMapperInterface.php
  Core/
    AbstractService.php
    Container.php                -- service container + activation/deactivation hooks
  Database/
    AbstractRepository.php
    LogRepository.php
    MappingRepository.php
    OptinRepository.php
    SchemaManager.php
  Queue/
    QueueService.php             -- WooCommerce trigger hooks + Action Scheduler jobs
  Services/
    ApiClientService.php         -- WhatsApp Cloud API HTTP client
    PhoneNormalizerService.php   -- E.164 normalisation (PHP 7.4 compatible)
    TemplateMapperService.php    -- order -> template variable resolver
```

#### WooCommerce dependency guard
`plugins_loaded` hook checks `class_exists('WooCommerce')`. If missing, shows an admin notice and aborts — no fatal errors.

### 6.2 Optional future companion SaaS (Laravel)
Move queue + webhooks + analytics to Laravel when there are paying users:
- More reliable job processing
- Central dashboard for agencies
- Cross-store analytics and billing hooks

---

## 7) Data model (v1)

> **Note:** Table prefix in production is `wp_` (standard WordPress). All table names use the `tmasd_` plugin prefix.

### 7.1 Table: `wp_tmasd_template_map`
Stores event → template mapping rules.

**Columns**
- `id` — bigint unsigned, PK, AUTO_INCREMENT
- `event_key` — varchar(191), e.g. `order_status_processing`
- `template_name` — varchar(191)
- `language` — varchar(20), default `en_US`
- `mapping_json` — longtext (JSON array of resolver keys, one per template placeholder position)
- `enabled` — tinyint(1), default `1`
- `created_at`, `updated_at` — datetime

**Indexes:** `PRIMARY KEY (id)`, `KEY event_key`, `KEY enabled`

### 7.2 Table: `wp_tmasd_logs`
Stores send attempt records and delivery outcomes.

**Columns**
- `id` — bigint unsigned, PK, AUTO_INCREMENT
- `order_id` — bigint unsigned, nullable
- `phone_e164` — varchar(32)
- `template_name` — varchar(191)
- `payload_json` — longtext
- `response_json` — longtext
- `status` — varchar(20): `queued|sent|delivered|read|failed|skipped`
- `wa_message_id` — varchar(191), nullable (set after successful API response)
- `error_code` — varchar(191), nullable
- `error_message` — text, nullable
- `created_at`, `updated_at` — datetime

**Indexes:** `PRIMARY KEY (id)`, `KEY order_id`, `KEY wa_message_id`, `KEY status`

### 7.3 Table: `wp_tmasd_optins`
Stores customer consent records.

**Columns**
- `id` — bigint unsigned, PK, AUTO_INCREMENT
- `user_id` — bigint unsigned, nullable
- `order_id` — bigint unsigned, nullable
- `phone_e164` — varchar(32)
- `consent` — tinyint(1), default `0`
- `consent_source` — varchar(20): `checkout|account|import|external`
- `consent_at` — datetime

**Indexes:** `PRIMARY KEY (id)`, `KEY phone_e164`, `KEY consent`

---

## 8) Key workflows

### 8.1 Order status → send WhatsApp template
1. Order status changes (`woocommerce_order_status_changed` hook, `QueueService`).
2. Map new status to event key; look up enabled mapping rule.
3. Schedule async Action Scheduler job (`TMASD_ACTION_SEND_TEMPLATE`).
4. Job fires: resolve billing phone to E.164 via `PhoneNormalizerService`.
5. If consent required: check `wp_tmasd_optins` — abort with `skipped` log row if missing.
6. Resolve template variables from order via `TemplateMapperService`.
7. Call WhatsApp Cloud API via `ApiClientService`; store log row with response.
8. On retriable failure (network / 429 / 5xx): reschedule up to 2 more attempts.
9. Meta sends webhook → `WebhookController` updates log row status (`sent`, `delivered`, `read`, `failed`).

### 8.2 Webhook verification (GET)
1. Meta sends `GET /wp-json/tmasd/v1/webhook?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...`.
2. Plugin compares `hub.verify_token` to stored `tmasd_webhook_verify_token` option.
3. On match: responds with `hub.challenge`. On mismatch: returns 403.

### 8.3 Webhook status update (POST)
1. Meta sends `POST /wp-json/tmasd/v1/webhook` with HMAC-SHA256 signature.
2. `verify_signature()`: compute HMAC of raw body with `app_secret`; reject if mismatch or secret not configured.
3. Validate `object === 'whatsapp_business_account'`; reject otherwise.
4. Walk `entry[].changes[].value.statuses[]`; map status to internal value; update log by `wa_message_id`. Unknown statuses produce no DB update.

### 8.4 COD confirmation [BACKLOG — not implemented]
1. New order created with COD payment method.
2. If consent exists, send COD confirmation template.
3. Unique token stored on order meta; confirmation URL endpoint marks `tmasd_cod_confirmed=1`.
4. Admin can view confirmation status on order edit screen.

---

## 9) Template variable resolver (v1)

Supported resolver keys (used as values in `mapping_json`):

| Key | Resolves to |
|---|---|
| `order_id` | Order database ID |
| `order_number` | Display order number (may differ from ID) |
| `order_total` | Order total amount |
| `order_currency` | Currency code (e.g. `INR`) |
| `billing_first_name` | Billing first name |
| `billing_last_name` | Billing last name |
| `billing_phone` | Billing phone (raw, before E.164 normalisation) |
| `billing_email` | Billing email address |
| `shipping_first_name` | Shipping first name |
| `shipping_last_name` | Shipping last name |
| `status` | Current order status slug |
| `site_name` | WordPress site name (`get_bloginfo('name')`) |

Rules:
- Missing or empty values resolve to empty string — they never cause a fatal error.
- Phone for sending is always normalised to E.164 separately via `PhoneNormalizerService`.
- `mapping_json` stores an ordered JSON array of resolver keys, one per template placeholder position (e.g. `["order_id","billing_first_name","order_total"]` maps to `{{1}}`, `{{2}}`, `{{3}}`).

---

## 10) Security requirements

### Implemented
- All admin pages and form handlers require capability `manage_woocommerce`.
- All write operations verify WordPress nonces.
- All output is escaped (`esc_html`, `esc_attr`, `esc_url`).
- Database writes use `$wpdb->prepare()` or `$wpdb->insert/update` (parameterised).
- Webhook POST: HMAC-SHA256 signature verification; fails closed when `app_secret` is absent.
- Secrets (`access_token`, `app_secret`): never echoed in form HTML; stored with `autoload=no`; never written to log rows.
- `mapping_json` allowlist strips unknown resolver keys before storage.
- `event_key` validated against an internal allowlist on save.
- Webhook payload: only `whatsapp_business_account` object type is processed.
- Webhook delivery status: unknown status values produce no DB update (no fallback to a misleading value).
- Retry logic: only retries on genuinely transient failures; permanent errors are not re-queued.
- WooCommerce dependency guard: plugin aborts cleanly if WooCommerce is inactive.

### Still required before v1.0
- Rate limiting on the send path (basic transient lock to prevent accidental floods).
- PHPCS + PHPCompatibilityWP pass against PHP 7.4 rules before WP.org submission.

---

## 11) Reliability requirements

- Queue processing is async and handled by Action Scheduler (resilient, survives page reloads).
- Retries: max 2 attempts; only on `http_code=0` (network/`WP_Error`), `429`, or `5xx`; base delay of 10 seconds.
- Idempotency: one enabled mapping per `event_key`; Action Scheduler `tmasd` group prevents duplicate queueing per request.

---

## 12) Observability & diagnostics

- **Health Check page** reports:
  - Credentials present (token presence only, never displayed)
  - Webhook configured status
  - Recent template mappings
  - Last error summary
- **Diagnostic export:**
  - Plugin / WP / WooCommerce / PHP version
  - Server info
  - Recent log summaries (no secrets or full phone numbers)
- **Log viewer:** full per-message history with status, error code, and timestamp.

---

## 13) Packaging & release

### WP.org release checklist
- [ ] Run `composer install --no-dev --optimize-autoloader` before building ZIP.
- [ ] Remove `__MACOSX/`, `.DS_Store`, `._*` files (`.gitattributes` export-ignore rules are in place).
- [ ] Exclude dev-only vendor packages (`squizlabs/php_codesniffer`, `wp-coding-standards/wpcs`, `phpcompatibility/*`).
- [ ] Exclude internal docs: `README.md`, `signals-project-spec.md`, `docs/`.
- [ ] Update `Tested up to` in `readme.txt` and plugin header after testing on latest WP.
- [ ] Run PHPCS + PHPCompatibilityWP against `7.4-` locally.
- [ ] Remove `composer.json`, `composer.lock`, `phpcs.xml.dist` from ZIP (handled by `.gitattributes`).

### Free vs Pro packaging (recommended for future)
- **Free (WP.org)**
  - Setup wizard
  - 1–2 dispatch rules
  - Basic logs (7 days)
- **Pro (paid)**
  - Unlimited dispatch rules + COD confirmation
  - Webhook delivery status tracking
  - Retries + extended log retention
  - Priority support

---

## 14) Acceptance criteria (v1)

Must pass before v1.0 release:
- A store can configure credentials without errors; all required fields are validated on save.
- When order changes to Processing / Completed / On-hold / Cancelled, the mapped template is queued and sent via the WhatsApp Cloud API.
- Logs show status and API response for each send attempt.
- Delivery status webhook updates the log row `status` by `wa_message_id`.
- When "Require Consent" is enabled and a consent record exists, message sends; when no record exists, message is skipped and logged.
- Checkout opt-in checkbox creates a consent record (BACKLOG — required to fully validate this criterion).
- No secrets are stored in DB logs nor rendered in admin form HTML.
- Plugin activates cleanly; deactivates cleanly; uninstalls completely (drops custom tables and all options).
- Plugin fails gracefully with a clear admin notice when WooCommerce is not installed.
- All admin actions are nonce-protected; all admin pages are capability-gated to `manage_woocommerce`.

---

## 15) "Start in a new chat" brief

**BRIEF**

> I'm building **Signals by Mediaable** (v0.2.0). Plugin slug: `signals-dispatch-woocommerce`. Stack: WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+, Action Scheduler. Composer vendor is bundled (no manual install needed). PHP namespace root: `TMASD\\Signals\\Dispatch`. DB tables: `wp_tmasd_logs`, `wp_tmasd_template_map`, `wp_tmasd_optins`. Security audits round 1 and 2 are complete.
>
> **Remaining work:** (1) Checkout opt-in checkbox to capture consent into `wp_tmasd_optins` (the enforcement + DB layer exist; only the UX collection is missing). (2) My Account opt-out toggle. (3) COD confirmation flow. (4) Clean WP.org release ZIP.
>
> Give me step-by-step prompts in small batches and help debug errors.
