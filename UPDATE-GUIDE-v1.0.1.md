# Maljani Travel Insurance Hub - Update 1.0.1 Implementation Guide

## 🚀 What Changed

This update implements **Phase 1 (Security)** improvements from the comprehensive plugin analysis. All changes are backwards-compatible and require no database migrations.

## 📦 New Features

### 1. Logging System

The plugin now includes a comprehensive logging system for debugging and monitoring.

#### Usage Example:

```php
// Log an error
Maljani_Logger::get_instance()->error('Payment processing failed', [
    'sale_id' => 123,
    'error_code' => 'PAYMENT_DECLINED'
]);

// Log a warning
Maljani_Logger::get_instance()->warning('Low stock alert');

// Log info
Maljani_Logger::get_instance()->info('New policy created', [
    'policy_id' => 456
]);

// Quick logging function
maljani_log('error', 'Something went wrong', ['context' => 'data']);
```

#### Viewing Logs:

Logs are stored in `/wp-content/uploads/maljani-logs/` with format `maljani-YYYY-MM-DD.log`

```php
// Get recent logs (last 50 lines)
$recent_logs = Maljani_Logger::get_instance()->get_recent_logs(50);

// Cleanup old logs (older than 30 days)
$deleted_count = Maljani_Logger::get_instance()->cleanup_old_logs();
```

**Note:** Logging only works when `WP_DEBUG` is enabled in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### 2. Caching System

Automatic caching reduces database queries and improves performance.

#### Usage Example:

```php
// Get premium with caching (24h cache)
$premium = Maljani_Cache::get_premium($policy_id, $days);

// Get policies with caching (1h cache)
$policies = Maljani_Cache::get_policies([
    'tax_query' => [
        [
            'taxonomy' => 'policy_region',
            'field' => 'term_id',
            'terms' => $region_id
        ]
    ]
]);

// Get regions with caching (1h cache)
$regions = Maljani_Cache::get_regions();

// Clear all caches (use after bulk updates)
Maljani_Cache::clear_all();

// Clear cache for specific policy (automatic on policy save)
Maljani_Cache::clear_policy_cache($policy_id);
```

#### Automatic Cache Invalidation:

The cache automatically clears when:
- A policy is updated or published
- Daily cleanup runs (scheduled via WP-Cron)

### 3. Enhanced Security

#### AJAX Nonce Protection

All AJAX endpoints now require nonce verification. Update your JavaScript:

**Before:**
```javascript
jQuery.post(ajaxurl, {
    action: 'maljani_get_policy_premium',
    policy_id: policyId,
    days: days
});
```

**After:**
```javascript
jQuery.post(ajaxurl, {
    action: 'maljani_get_policy_premium',
    policy_id: policyId,
    days: days,
    security: '<?php echo wp_create_nonce("maljani_premium_nonce"); ?>'
});
```

Or in PHP when enqueuing scripts:
```php
wp_localize_script('your-script', 'maljaniData', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('maljani_premium_nonce')
]);
```

Then in JS:
```javascript
jQuery.post(maljaniData.ajax_url, {
    action: 'maljani_get_policy_premium',
    policy_id: policyId,
    days: days,
    security: maljaniData.nonce
});
```

#### PDF Access Control

PDFs now require authentication and authorization:

- Users must be logged in
- Users can only access PDFs for:
  - Sales they created (if agent)
  - Sales where their email matches (if customer)
  - All sales (if admin)

No code changes needed - works automatically.

#### Date Validation

The sales form now includes comprehensive date validation:

- Dates must be in correct format (YYYY-MM-DD)
- Return date must be after departure date
- Departure date cannot be in the past
- Maximum trip duration: 365 days

This validation happens automatically in the form submission handler.

## 🔍 Testing the Updates

### 1. Test Logging

```php
// Add this to your theme's functions.php temporarily
add_action('init', function() {
    if (isset($_GET['test_maljani_log'])) {
        Maljani_Logger::get_instance()->info('Test log entry', [
            'timestamp' => current_time('mysql'),
            'user' => get_current_user_id()
        ]);
        echo "Log written! Check /wp-content/uploads/maljani-logs/";
        exit;
    }
});
```

Visit: `yoursite.com/?test_maljani_log=1`

### 2. Test Caching

```php
// Test premium caching
add_action('init', function() {
    if (isset($_GET['test_cache'])) {
        $policy_id = 1; // Change to valid policy ID
        $days = 10;
        
        // First call - cache miss
        $start = microtime(true);
        $premium1 = Maljani_Cache::get_premium($policy_id, $days);
        $time1 = (microtime(true) - $start) * 1000;
        
        // Second call - cache hit
        $start = microtime(true);
        $premium2 = Maljani_Cache::get_premium($policy_id, $days);
        $time2 = (microtime(true) - $start) * 1000;
        
        echo "First call (cache miss): {$time1}ms<br>";
        echo "Second call (cache hit): {$time2}ms<br>";
        echo "Premium: $premium1<br>";
        echo "Speed improvement: " . round($time1 / $time2, 2) . "x faster";
        exit;
    }
});
```

Visit: `yoursite.com/?test_cache=1`

### 3. Test Security

**Test PDF Access:**
1. Create a test sale
2. Log out
3. Try to access the PDF URL directly
4. You should see: "You must be logged in to access this document."

**Test AJAX Security:**
1. Open browser console on a page with the sales form
2. Try calling the AJAX endpoint without nonce:
```javascript
jQuery.post(ajaxurl, {
    action: 'maljani_get_policy_premium',
    policy_id: 1,
    days: 10
}, function(response) {
    console.log(response);
});
```
3. Should fail with error

## 🛠️ Troubleshooting

### Logs Not Writing

**Problem:** No log files appearing in `/wp-content/uploads/maljani-logs/`

**Solution:**
1. Check that `WP_DEBUG` is enabled in `wp-config.php`
2. Verify upload directory is writable: `chmod 755 wp-content/uploads`
3. Check PHP error logs for permission errors

### Cache Not Working

**Problem:** Queries still slow despite caching

**Solution:**
1. Verify object caching is available (check for Memcached/Redis)
2. Clear all caches: `Maljani_Cache::clear_all()`
3. Check transients table size in database

### AJAX Errors

**Problem:** "Invalid nonce" or "Security check failed"

**Solution:**
1. Clear browser cache
2. Ensure nonce is being passed correctly
3. Check if user is logged in (for logged-in-only endpoints)
4. Verify nonce action name matches: `maljani_premium_nonce`

## 📊 Performance Improvements

Expected performance gains:

| Operation | Before | After | Improvement |
|-----------|--------|-------|-------------|
| Premium calculation | ~5-10ms | ~0.5ms | **10-20x faster** |
| Policy list query | ~50-100ms | ~5-10ms | **10x faster** |
| Region list query | ~20-30ms | ~2ms | **10-15x faster** |

*Actual results depend on server configuration and dataset size.*

## 🔐 Security Checklist

After updating, verify:

- [ ] AJAX endpoints require nonce
- [ ] PDF URLs require authentication
- [ ] Only authorized users can access PDFs
- [ ] Date validation prevents invalid submissions
- [ ] All user inputs are sanitized
- [ ] Logs directory is protected by .htaccess

## 📚 Additional Resources

- **Full Documentation:** See `DOCUMENTATION.md`
- **Shortcode Guide:** See `SHORTCODES.md`
- **Setup Guide:** See `SETUP-GUIDE.md`
- **Changelog:** See `CHANGELOG.md`

## 🎯 What's Next?

Future updates will include:

**Phase 2 - Additional Performance:**
- Asset minification
- Database query optimization
- Lazy loading for images

**Phase 3 - Code Quality:**
- PHPDoc for all methods
- Unit tests
- Code standards compliance

**Phase 4 - UX Improvements:**
- Complete i18n support
- Modern notification system
- Analytics dashboard

## ⚠️ Important Notes

1. **Backwards Compatible:** All changes are backwards compatible
2. **No Database Changes:** No migration required
3. **Safe to Update:** Can update on production without downtime
4. **Logging Opt-in:** Only logs when WP_DEBUG is enabled
5. **Cache Automatic:** Caching works automatically, no configuration needed

## 💡 Best Practices

1. **Enable Logging in Development:**
   ```php
   // wp-config.php for development
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Monitor Log Size:**
   - Set up automatic cleanup with WP-Cron
   - Logs auto-delete after 30 days
   - Manual cleanup: `Maljani_Logger::get_instance()->cleanup_old_logs()`

3. **Clear Cache After Bulk Updates:**
   ```php
   // After importing many policies
   Maljani_Cache::clear_all();
   ```

4. **Security Headers:**
   Add to `.htaccess` for extra protection:
   ```apache
   <FilesMatch "\.log$">
       Require all denied
   </FilesMatch>
   ```

---

**Questions or Issues?**
- Email: denisdekemet@gmail.com
- GitHub: https://github.com/kanji8210/maljani_travel_insurance_hub
- Website: https://kipdevwp.tech/
