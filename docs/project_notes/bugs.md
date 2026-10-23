# Bug Log

Log recurring bugs, their solutions, and prevention notes here.

## Template
### YYYY-MM-DD - Brief Bug Description
- **Issue**: What went wrong
- **Root Cause**: Why it happened
- **Solution**: How it was fixed
- **Prevention**: How to avoid it in the future

---

### 2026-10-23 - Certificate Endpoint Hid Missing Policy Records
- **Issue**: Certificate requests returned HTTP 500 with only `Certificate unavailable`, while the frontend incorrectly logged a payment-initiation failure.
- **Root Cause**: The certificate renderer returned an empty string when the sale's linked policy post could not be found. Both the admin print action and REST endpoint lacked an actionable error.
- **Solution**: Return a structured error with HTTP 409 for missing policy links, log only sale/policy IDs, display repair guidance in REST and admin responses, and label frontend failures by the requested operation. Reject non-positive policy IDs before calling `get_post` so WordPress cannot substitute the current post.
- **Repair**: On the affected WordPress installation, find the sale under Policy Sales and check its Policy selection. Restore the original deleted plan or relink the sale to the correct existing plan and save, then retry the certificate. Do not select an arbitrary replacement or change payment status. Local sale data may differ from production.
- **Prevention**: Preserve policy records referenced by historical sales. Missing plan/provider data must not produce a success-shaped certificate.

### 2026-03-10 - Chat System Connection Errors
- **Issue**: ERR_CERT_AUTHORITY_INVALID and 400 Bad Request in chat system.
- **Root Cause**: SSL configuration on localhost and potential conflicts with REST API polling.
- **Solution**: Investigated JavaScript polling logic and REST API consistency.
- **Prevention**: Ensure consistent API endpoints and SSL certificate validity for local environment.

### 2026-07-21 - Pesapal Token Error Was Blank
- **Issue**: Proceeding to payment showed `Failed to retrieve Pesapal token:` without the actual Pesapal/API error.
- **Root Cause**: Gateway only read `$body->error->message`; Pesapal can return other response shapes such as `message`, `error_description`, plain text, invalid JSON, or just an HTTP status.
- **Solution**: Trim saved credentials, validate HTTP status before accepting a token, centralize response error extraction, and add a settings-page button to test the saved Pesapal connection.
- **Prevention**: Use the settings connection test after changing key, secret, or environment; token/IPN/order errors now include HTTP status and response message.

### 2026-09-12 - Pesapal Amount Limit Returned With HTTP 200
- **Issue**: Payment initiation showed `Order creation failed: HTTP 200: Transaction amount exceeds limit.Contact support for assistance`.
- **Root Cause**: Pesapal accepted the HTTP request but rejected the order at the business layer because the amount exceeded the merchant account limit. The response had no `redirect_url`, which the API 3.0 guide requires for a successfully created order.
- **Solution**: Require a non-empty `redirect_url`, classify the provider message as `pesapal_amount_limit`, return HTTP 422 with customer-safe guidance, and log sale ID, amount, currency, and environment for diagnosis without changing the charge.
- **Prevention**: Confirm the logged amount and ISO currency before contacting Pesapal to raise the merchant transaction limit. A temporary, explicitly requested test override currently charges KES 100 in every environment while retaining the full sale value; remove the override before real customer payments resume.
