# WooCommerce Cornerstone Quarry Gateway

A custom WooCommerce payment gateway plugin that integrates with [Cornerstone Payment Systems](https://www.cornerstonepayments.com) via the Quarry REST API.

## Features

- **Secure Payment Processing** — Direct integration with Cornerstone Quarry API using Basic Authentication
- **Card Validation** — Luhn algorithm validation for card numbers and automatic expiry date validation
- **Idempotent Requests** — Built-in request ID handling to prevent duplicate charges
- **Refund Support** — Full refund capability directly from WooCommerce order admin
- **Order Tracking** — Transaction IDs automatically stored in order meta for audit trails
- **Sandbox & Production** — Toggle between environments via API credentials
- **Error Handling** — Comprehensive logging and customer-friendly error messages

## Requirements

- **WordPress** 5.0+
- **WooCommerce** 6.0+
- **PHP** 7.4+
- **cURL** extension enabled
- **Cornerstone Payment Systems** merchant account with API credentials

## Installation

1. **Download the plugin**
   - Clone this repository or download `wc-cornerstone-quarry-gateway.php`

2. **Add to WordPress**
   - Upload to `/wp-content/plugins/cornerstone-payment-gateway/`
   - Or upload as single file: `/wp-content/plugins/`

3. **Activate**
   - Go to WordPress Admin → **Plugins**
   - Find "WooCommerce Cornerstone Quarry Gateway"
   - Click **Activate**

4. **Configure**
   - Go to **WooCommerce** → **Settings** → **Payments**
   - Find "Cornerstone Quarry Gateway"
   - Click **Manage**
   - Enter your API credentials (see below)

## Configuration

### Getting API Credentials

1. Log in to your [Cornerstone Merchant Dashboard](https://pay.cornerstone.cc)
2. Navigate to **API Keys** or **Settings** → **Integrations**
3. Generate new API credentials (Username & Password for Basic Auth)
4. Copy Merchant ID (MID) for reference

### Plugin Settings

| Setting | Description |
|---------|-------------|
| **Enable/Disable** | Toggle payment method on/off |
| **Title** | Text shown to customers at checkout |
| **Description** | Additional checkout message |
| **API Username** | Your Cornerstone API username |
| **API Password** | Your Cornerstone API password |
| **Sandbox Mode** | Enable for testing (API endpoint: sandbox mode) |
| **Debug Log** | Enable to log API requests/responses |

## API Integration Details

**Endpoint:** `https://api.cornerstone.cc/v1/transactions`

**Authentication:** HTTP Basic Auth (Username:Password encoded in Base64)

**Request Format:**
```json
{
  "amount": 1000,
  "currency": "USD",
  "card_number": "4111111111111111",
  "card_expiry_month": "12",
  "card_expiry_year": "2025",
  "card_cvc": "123",
  "request_id": "unique-idempotent-id",
  "order_id": "WC-ORDER-123"
}
```

**Refunds:** PATCH request to `/v1/transactions/{transaction_id}/refund`

## Usage

Once activated and configured, the gateway appears as a payment option at WooCommerce checkout. Customers can:
1. Enter payment details securely
2. Complete checkout
3. Receive order confirmation with transaction ID

### Admin Refunds

To refund an order from WooCommerce Admin:
1. Go to **Orders** → select order
2. Scroll to **Order Actions** → click **Refund**
3. Enter refund amount
4. Click **Refund** — automatically processed via Cornerstone API

## Known Limitations

- Expiry year format (2-digit vs 4-digit) determined by API documentation
- Sandbox testing recommended before production use
- Requires valid Cornerstone merchant account with API access

## Testing

### Sandbox Credentials

Use test card numbers provided by Cornerstone Payment Systems:
- **Visa Test:** `4111 1111 1111 1111` | Exp: Any future date | CVC: Any 3 digits
- See Cornerstone documentation for additional test cards

### Order Meta

Transaction details stored in order meta:
- `_cornerstone_transaction_id` — Unique Cornerstone transaction ID
- `_cornerstone_request_id` — Idempotent request ID
- `_cornerstone_status` — Transaction status (approved, declined, etc.)

## Troubleshooting

**Payment declined?**
- Verify API credentials are correct
- Check Cornerstone dashboard for error logs
- Enable debug logging and review WooCommerce logs

**Refund not processing?**
- Confirm transaction ID is valid
- Check user permissions
- Review API logs for error details

**Cards failing validation?**
- Ensure card numbers pass Luhn validation
- Verify expiry date is in future (MM/YYYY format)
- Check CVC is 3-4 digits

## Support

For issues with:
- **This plugin:** Open an issue on GitHub
- **Cornerstone API:** Contact [Cornerstone Support](https://www.cornerstonepayments.com/support)
- **WooCommerce integration:** Refer to [WooCommerce Docs](https://docs.woocommerce.com)

## License

This plugin is licensed under the **MIT License**. See `LICENSE` file for details.

## Disclaimer

This is a custom payment gateway implementation for Cornerstone Payment Systems. Ensure PCI compliance and test thoroughly in sandbox mode before going live. The author is not responsible for transaction errors or security issues arising from misuse.

---

**Author:** [@Suzuya96](https://github.com/Suzuya96)  
**Repository:** [cornerstone-payment-gateway](https://github.com/Suzuya96/cornerstone-payment-gateway)
