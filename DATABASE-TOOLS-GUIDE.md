# Database Management Tools - Quick Guide

## Location
**WordPress Admin > Maljani Travel > Database Tools**

## Features

### 1. Database Tables Management

#### Tables Created:
- **policy_sale** - Stores policy sales and customer information
- **maljani_api_keys** - Manages API keys for integrations

#### Table Status Monitor:
- ✓ **Exists** - Table is present in database
- ✗ **Missing** - Table needs to be created
- ✓ **OK** - Structure matches schema
- ⚠ **Needs Update** - Structure requires updating

#### Actions:
- **Create/Update All Tables** - Creates missing tables or updates existing ones
- **Repair/Update All Tables** - Fixes structure issues
- Individual table actions available per table

### 2. API Key Management

#### Generate New Keys:
1. Enter a descriptive name (e.g., "Production API", "Mobile App")
2. Select environment (Sandbox or Production)  
3. Click "Generate API Key"
4. **Important:** Save both keys immediately - secret key shown only once

#### Key Format:
- **API Key:** `mlj_[48 characters]`
- **Secret Key:** `mljs_[64 characters]`

#### Manage Existing Keys:
- **Activate/Deactivate** - Toggle key status without deletion
- **Delete** - Permanently remove key
- **View Details** - See creation date, environment, status

### 3. Database Schema

#### policy_sale Table Fields:
```
- id (auto-increment)
- policy_id
- policy_number
- region
- premium (decimal)
- days
- departure/return (dates)
- insured_names, dob, passport, national_id
- contact: phone, email, address
- country_of_origin
- agent_id, agent_name
- payment: amount_paid, reference, status
- policy_status
- terms
- timestamps: created_at, updated_at
```

#### maljani_api_keys Table Fields:
```
- id (auto-increment)
- key_name
- api_key (unique)
- secret_key
- environment (sandbox/production)
- status (active/inactive)
- timestamps: created_at, updated_at
```

## Common Tasks

### First Time Setup:
1. Go to **Maljani Travel > Database Tools**
2. Click **"Create/Update All Tables"**
3. Verify all tables show ✓ Exists and ✓ OK
4. Generate initial API keys if needed

### After Plugin Update:
1. Check Database Tools page
2. Look for ⚠ **Needs Update** warnings
3. Click **"Create/Update All Tables"** to update schema
4. Verify all tables show ✓ OK

### Generate Production API Key:
1. Go to **API Keys Management** section
2. Enter key name: "Production API"
3. Select environment: **Production**
4. Click **Generate API Key**
5. **Copy and save both keys immediately**
6. Store keys in secure password manager

### Troubleshooting Missing Tables:
1. Check table status in Database Tools
2. Note which tables are missing
3. Click **"Create/Update All Tables"**
4. Refresh page to verify creation
5. If issues persist, check WordPress error logs

## Security Notes

⚠️ **Important Security Practices:**
- Never share API keys publicly
- Use sandbox keys for testing
- Deactivate unused keys instead of leaving them active
- Regularly rotate production keys
- Store secret keys securely (they're only shown once)
- Only authorized administrators should access Database Tools

## API Key Statuses

- 🟢 **Active** - Key is functional and can be used
- ⚪ **Inactive** - Key is disabled, API calls will fail
- **Production** (Red badge) - Live environment key
- **Sandbox** (Blue badge) - Testing environment key

## Support

For issues or questions:
- Check WordPress error logs
- Verify user has 'manage_options' capability
- Review DOCUMENTATION.md for detailed information
