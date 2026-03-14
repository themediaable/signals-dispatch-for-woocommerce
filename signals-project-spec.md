# Signals by Mediaable — Project Specification (v1)

**Project codename:** Signals  
**Brand (public):** Signals by Mediaable  
**Company:** The Media Able  
**Primary domain:** themediaablesignals.com  
**Initial release (v1):** Signals **Dispatch** for WooCommerce (WhatsApp Cloud API)

---

## 1) Executive summary

**Signals** is a platform-neutral product suite for **automations and customer notifications** across commerce platforms (WooCommerce first, Shopify later) and messaging channels (WhatsApp first, then email/SMS/push).

**v1 goal:** ship a reliable, secure, low-support WordPress plugin that sends WhatsApp **template messages** for WooCommerce order updates and COD confirmation using **Bring-Your-Own WhatsApp Cloud API** credentials.

---

## 2) Product pillars

1. **Platform-neutral brand**
   - “Signals” stays relevant as you add Shopify and other connectors.

2. **Low-support by design**
   - Setup wizard, health checks, clear error messages, diagnostic export.

3. **Reliability**
   - Queue + retries, delivery status updates via webhook, logs for audits.

4. **Compliance-first**
   - Opt-in tracking, template messaging rules, safe defaults.

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
- Order status updates increase trust and reduce “Where is my order?” queries.
- COD confirmation reduces cancellations/refusals at delivery.

---

## 4) Product scope

### 4.1 v1 Module: Dispatch (WooCommerce → WhatsApp)
**Dispatch** automates transactional WhatsApp messages triggered by WooCommerce events.

#### v1 features (MVP)
**A) Setup & connection**
- Setup Wizard (admin UI) that configures:
  - WhatsApp Cloud API: `phone_number_id`, `waba_id`, `access_token`
  - Webhook verify token and callback URL display
  - Basic defaults (timezone, logs retention, opt-in requirement)

**B) Template mapping**
- Map WooCommerce events → WhatsApp template name + language.
- Define variable mapping for template placeholders (e.g., `{{1}}`, `{{2}}`) to order/customer fields.

**C) Triggers**
- Send on these order status events:
  - Processing
  - Completed
  - On-hold
  - Cancelled
- COD confirmation trigger:
  - On new order if payment method is COD (configurable)

**D) Queue + retries**
- Asynchronous sending using Action Scheduler (WooCommerce ecosystem standard).
- Retry transient failures with exponential backoff.
- Idempotency: avoid duplicate sends per order-event-template.

**E) Webhook delivery status**
- REST webhook endpoint to receive message status updates.
- Update log records with final delivery status and error metadata.

**F) Logging**
- Structured logs: payload, response, wa_message_id, status, error codes.
- Never store secrets (access tokens) in logs.

**G) Consent / opt-in**
- Checkout opt-in checkbox (“Get order updates on WhatsApp”).
- Store consent timestamp and source.
- Default behavior: only send if opt-in exists (configurable override for stores with external consent systems).

#### v1 explicit non-goals
- Marketing campaigns/broadcasts
- Abandoned cart automation
- Two-way chat / shared inbox
- AI chatbot inside WhatsApp
- Shopify connector (planned for later)

---

## 5) Roadmap (high-level)

### v1.1 (post-MVP hardening)
- Abandoned cart reminder (utility template)
- “Manual send” from order screen (admin action)
- Template packs + guided creation steps
- Improved diagnostics bundle export

### v1.2 (scale)
- Multi-number / multi-store support (Business tier)
- Segments + rules (simple IF/THEN)
- Email channel connector (optional)

### v2 (platform expansion)
- Shopify connector (orders webhooks → Signals)
- Additional channels: SMS, email providers, push notifications
- Optional SaaS dashboard (Laravel) for analytics + centralized settings

---

## 6) Technical architecture (v1)

### 6.1 WordPress plugin (single-product build)
**Why:** fastest distribution, easiest adoption for WooCommerce users, economical to run as a solo founder.

#### Components
- **Admin UI**
  - Setup Wizard
  - Dispatch Rules / Template Mapping
  - Logs viewer
  - Health Check / Diagnostics
- **Core Engine**
  - WooCommerce triggers
  - Template resolver (order → variable parameters)
  - WhatsApp Cloud API client
- **Queue**
  - Action Scheduler jobs for send operations
- **Webhooks**
  - REST endpoint to receive status callbacks
- **Storage**
  - Custom DB tables (for performance + structured querying)

### 6.2 Optional future companion SaaS (Laravel)
Move queue + webhooks + analytics to Laravel when you have paying users:
- more reliable job processing
- central dashboard for agencies
- cross-store analytics and billing hooks

---

## 7) Data model (v1)

### 7.1 Table: `wp_msd_template_map`
Stores event → template mapping.

**Columns**
- `id` (PK)
- `event_key` (varchar) e.g. `order_status_processing`
- `template_name` (varchar)
- `language` (varchar) e.g. `en_US`
- `mapping_json` (longtext) (placeholder → resolver key)
- `enabled` (tinyint)
- `created_at`, `updated_at` (datetime)

### 7.2 Table: `wp_msd_logs`
Stores send attempt logs and outcomes.

**Columns**
- `id` (PK)
- `order_id` (bigint, nullable)
- `event_key` (varchar, nullable)
- `phone_e164` (varchar)
- `template_name` (varchar)
- `payload_json` (longtext)
- `response_json` (longtext)
- `status` (varchar) `queued|sent|delivered|read|failed`
- `wa_message_id` (varchar, nullable)
- `error_code` (varchar, nullable)
- `error_message` (text, nullable)
- `attempt_count` (int)
- `created_at`, `updated_at` (datetime)

### 7.3 Table: `wp_msd_optins`
Stores consent.

**Columns**
- `id` (PK)
- `user_id` (bigint, nullable)
- `order_id` (bigint, nullable)
- `phone_e164` (varchar)
- `consent` (tinyint)
- `consent_source` (varchar) `checkout|account|import|external`
- `consent_at` (datetime)

---

## 8) Key workflows

### 8.1 Order status → send WhatsApp template
1. Order status changes (Woo hook).
2. Determine if event is mapped and enabled.
3. Normalize phone to E.164.
4. Confirm consent policy (opt-in exists or override enabled).
5. Resolve template variables from order.
6. Enqueue Action Scheduler job.
7. Job calls WhatsApp Cloud API.
8. Store response and message id in logs.
9. Webhook updates delivery status.

### 8.2 COD confirmation
1. New order created with COD payment method.
2. If consent exists, send COD confirmation template.
3. Provide confirmation mechanism (v1 can be simple):
   - Unique token stored on order meta
   - Confirmation URL endpoint that marks order meta `msd_cod_confirmed=1`
4. Admin can view confirmation status on order.

---

## 9) Template variable resolver (v1)

Supported resolver keys:
- `customer_name`
- `order_id`
- `order_total`
- `order_currency`
- `order_date`
- `billing_phone`
- `billing_email`
- `site_name`

Rules:
- Missing values resolve to empty strings.
- Phone normalization must handle country code defaults (admin setting) but allow explicit +CC numbers.

---

## 10) Security requirements

- All admin actions require capability checks (e.g., `manage_woocommerce` or `manage_options`).
- All forms use nonces + sanitize/escape input/output.
- REST endpoints:
  - Webhook: verify token and strict payload validation.
  - Admin health endpoint: admin-only auth.
- Never store API tokens in logs or export bundles.
- Avoid leaking PII in error messages; keep logs structured but safe.
- Rate limiting (basic transient limit) to prevent accidental floods.

---

## 11) Reliability requirements

- Queue processing must be async and resilient.
- Retries:
  - Only for transient HTTP/network errors or retryable API codes.
  - Exponential backoff.
  - Max retry attempts configurable (default 3).
- Idempotency:
  - Prevent duplicate sends for same order + event + template within a time window.

---

## 12) Observability & diagnostics

- Health Check page must report:
  - Token present (not displayed), last API connectivity check status
  - Webhook configured and reachable
  - Templates mapped and enabled
  - Last error summary
- Diagnostic export:
  - Plugin version, WP/Woo versions, PHP version, server info
  - Recent log summaries (no secrets)
  - Webhook status notes

---

## 13) Packaging & release

### Free vs Pro packaging (recommended)
- **Free (WP.org)**
  - Setup wizard
  - 1–2 mappings
  - Basic logs (7 days)
- **Pro (paid)**
  - All mappings + COD confirmation
  - Webhook delivery statuses
  - Retries + longer logs
  - Priority support

---

## 14) Acceptance criteria (v1)

Must pass:
- A store can configure credentials and send a test template message successfully.
- When order changes to Processing/Completed/On-hold/Cancelled, mapped template is queued and sent.
- Logs show status and API response for each send.
- Delivery status webhook updates log row by message id.
- Opt-in checkbox stores consent; messages do not send when opt-in is required and missing.
- No secrets are stored in DB logs or rendered in UI.
- Plugin works on typical WooCommerce setups without fatal errors; fails gracefully with helpful admin notices.

---

## 15) “Start in a new chat” brief

**BRIEF**
“I’m building **Signals by Mediaable**. v1 module is **Dispatch for WooCommerce** (Woo → WhatsApp Cloud API). MVP includes setup wizard, template mapping, Woo triggers, Action Scheduler queue, webhook delivery updates, logs, and opt-in. Give me step-by-step prompts for Cursor/VS Code in small batches and help debug errors.”
