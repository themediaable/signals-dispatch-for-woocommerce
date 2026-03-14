# WhatsApp Business API Setup Guide

This guide walks you through obtaining the required credentials to use Signals Dispatch for WooCommerce.

## Prerequisites

- A Facebook account
- A Meta Business Account (or create one during setup)
- A phone number that can receive SMS for verification

## Step 1: Create a Meta Developer Account

1. Go to [developers.facebook.com](https://developers.facebook.com)
2. Click **Get Started** and log in with your Facebook account
3. Complete the developer registration process

## Step 2: Create a Meta App

1. Go to [developers.facebook.com/apps](https://developers.facebook.com/apps)
2. Click **Create App**
3. Select **Other** for use case, then click **Next**
4. Select **Business** as the app type, then click **Next**
5. Enter your app name (e.g., "My Store Notifications")
6. Select or create a Business Portfolio
7. Click **Create App**

## Step 3: Add WhatsApp Product

1. In your app dashboard, find **WhatsApp** in the products list
2. Click **Set Up**
3. This will add WhatsApp to your app and open the API Setup page

## Step 4: Get Your Credentials

On the **WhatsApp → API Setup** page, you'll find:

### Phone Number ID

- Located under "From" phone number dropdown
- A numeric ID like `1234567890123456`
- This identifies your WhatsApp Business phone number

### WhatsApp Business Account ID (WABA ID)

- Shown on the API Setup page as "WhatsApp Business Account ID"
- A numeric ID like `9876543210987654`

### Access Token

**Temporary Token (for testing):**
- Click **Generate** on the API Setup page
- Valid for 24 hours
- Good for initial testing

**Permanent Token (for production):**
1. Go to [business.facebook.com/settings](https://business.facebook.com/settings)
2. Navigate to **Users → System Users**
3. Click **Add** to create a new system user
4. Set role to **Admin**
5. Click **Add Assets** → Select your WhatsApp Business Account → Enable full control
6. Click **Generate New Token**
7. Select your app
8. Add these permissions:
   - `whatsapp_business_messaging`
   - `whatsapp_business_management`
9. Click **Generate Token**
10. Copy and save the token securely (you won't see it again)

### Webhook Verify Token

This is a secret string that **you create yourself**. It's used to verify that webhook requests come from Meta.

Example: `my_store_webhook_secret_2026`

Requirements:
- Can be any alphanumeric string
- Should be unique and hard to guess
- Keep it secret

## Step 5: Add Test Recipients (Sandbox)

Before your app is approved for production, you can only message phone numbers you've added as test recipients:

1. On the API Setup page, find "To" field
2. Click **Manage phone number list**
3. Click **Add phone number**
4. Enter the phone number and verify via SMS code
5. Repeat for up to 5 test numbers

## Step 6: Configure Webhooks

1. In your Meta App, go to **WhatsApp → Configuration**
2. Click **Edit** on the Webhook section
3. Enter your callback URL:
   ```
   https://yoursite.com/wp-json/signals/v1/webhook
   ```
4. Enter the **Verify Token** you created
5. Click **Verify and Save**
6. Subscribe to these webhook fields:
   - `messages` (for delivery status updates)

> **Note:** For local development, use a tunneling service like [ngrok](https://ngrok.com) to expose your local WordPress to the internet.

## Step 7: Create Message Templates

WhatsApp requires pre-approved templates for business-initiated messages:

1. Go to **WhatsApp → Message Templates**
2. Click **Create Template**
3. Select **Utility** category
4. Name your template (e.g., `order_confirmation`)
5. Add your message with variables:
   ```
   Hello {{1}}, your order #{{2}} for {{3}} has been confirmed!
   ```
6. Submit for approval (usually takes minutes to hours)

### Template Variable Mapping

In Signals Dispatch, map variables using a JSON array:
```json
["billing_first_name", "order_number", "order_total"]
```

This maps:
- `{{1}}` → Customer's first name
- `{{2}}` → Order number
- `{{3}}` → Order total

## Credential Summary

| Credential | Example | Where to Enter |
|------------|---------|----------------|
| Phone Number ID | `1234567890123456` | Signals → Setup |
| WABA ID | `9876543210987654` | Signals → Setup |
| Access Token | `EAABs...ZD` | Signals → Setup |
| Webhook Verify Token | `my_secret_token` | Signals → Setup & Meta App |

## Troubleshooting

### "Invalid Access Token"
- Token may have expired (temporary tokens last 24 hours)
- Generate a new permanent token via System User

### "Phone number not registered"
- Ensure you're using the Phone Number ID, not the actual phone number
- Verify the phone number is properly set up in WhatsApp Business

### "Template not found"
- Check template name matches exactly (case-sensitive)
- Ensure template status is "Approved"
- Verify you're using the correct language code

### Webhook verification failing
- Ensure your site is accessible from the internet
- Verify token must match exactly in both places
- Check for trailing spaces in the verify token

## Resources

- [WhatsApp Business Platform Documentation](https://developers.facebook.com/docs/whatsapp)
- [Message Templates Guide](https://developers.facebook.com/docs/whatsapp/message-templates)
- [Webhooks Reference](https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks)
- [Rate Limits & Best Practices](https://developers.facebook.com/docs/whatsapp/cloud-api/overview#rate-limits)
