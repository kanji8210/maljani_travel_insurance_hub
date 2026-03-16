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
