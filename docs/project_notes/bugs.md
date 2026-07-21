# Bug Log

Log recurring bugs, their solutions, and prevention notes here.

## Template
### YYYY-MM-DD - Brief Bug Description
- **Issue**: What went wrong
- **Root Cause**: Why it happened
- **Solution**: How it was fixed
- **Prevention**: How to avoid it in the future

---

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
