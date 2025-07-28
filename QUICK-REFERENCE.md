# 🚀 Quick Shortcode Reference - Maljani Travel Insurance Hub

## Available Shortcodes

### 🛒 Sales Form
```
[maljani_policy_sale]
```
**Purpose:** Complete insurance policy sales form  
**Features:** Auto premium calculation, client validation, payment processing  
**Attributes:** `policy_id`, `destination`, `agent_id`

---

### 👤 User Dashboard  
```
[maljani_user_dashboard]
```
**Purpose:** Dashboard interface for agents and customers  
**Features:** Policy management, document downloads, sales history  
**Roles:** Different interface for Agent vs Customer roles

---

### 📝 Agent Registration
```
[maljani_agent_register]
```
**Purpose:** Registration form for new insurance agents  
**Features:** Professional validation, role assignment, email confirmation  
**Creates:** New user with "Agent" role

---

### 🔄 Legacy Sales Form
```
[maljani_sales_form]
```
**Purpose:** Backward compatibility alias for `[maljani_policy_sale]`  
**Note:** Use `[maljani_policy_sale]` for new implementations

---

### 🎨 Icon Display
```
[maljani_icon]
```
**Purpose:** Display icons with text and styling options  
**Features:** Dashicons, FontAwesome, custom icons, multiple sizes  
**Attributes:** `name`, `size`, `color`, `text`, `link`, `style`

---

## Quick Setup Guide

1. **Create Pages:**
   ```
   /buy-insurance/     → [maljani_policy_sale]
   /agent-dashboard/   → [maljani_user_dashboard]
   /become-agent/      → [maljani_agent_register]
   ```

2. **Configure in Admin:**
   - Go to **Maljani Settings**
   - Assign pages to shortcodes
   - Configure email notifications

3. **Test Setup:**
   - Visit diagnostic page: `/wp-admin/admin.php?page=maljani-diagnostic`
   - Verify all shortcodes work properly

## Advanced Usage Examples

### Sales Form with Pre-selection
```
[maljani_policy_sale policy_id="123" destination="Europe"]
```

### Dashboard for Specific Role
```
[maljani_user_dashboard role="agent"]
```

### Custom Styling
```html
<div class="custom-insurance-form">
    [maljani_policy_sale]
</div>
```

### Icon Examples
```
<!-- Basic icon -->
[maljani_icon name="star-filled"]

<!-- Icon with text -->
[maljani_icon name="shield" text="Secure Coverage" size="large"]

<!-- Icon with link -->
[maljani_icon name="phone" text="Call Us" link="tel:+1234567890" color="#0073aa"]

<!-- FontAwesome icon -->
[maljani_icon name="plane" style="fontawesome" text="Travel Ready"]
```

## CSS Classes for Customization

### Plugin Isolation Container
```css
/* Main isolation container */
.maljani-plugin-container { }

/* Sales Form - Protected from theme styles */
.maljani-plugin-container .maljani-sales-form-container { }
.maljani-plugin-container .maljani-premium-display { }

/* Dashboard - Theme-independent styling */
.maljani-plugin-container .maljani-dashboard { }
.maljani-plugin-container .maljani-policy-list { }

/* Registration - Isolated form styles */
.maljani-plugin-container .maljani-registration-form { }

/* Icons - Style-protected display */
.maljani-plugin-container .maljani-icon { }
.maljani-plugin-container .maljani-icon-wrapper { }
.maljani-plugin-container .maljani-icon-link { }
.maljani-plugin-container .maljani-icon.size-small { }
.maljani-plugin-container .maljani-icon.size-medium { }
.maljani-plugin-container .maljani-icon.size-large { }
.maljani-plugin-container .maljani-icon.size-xl { }

/* Buttons - Theme-conflict protected */
.maljani-plugin-container .maljani-btn { }
.maljani-plugin-container .maljani-btn.secondary { }

/* Notifications - Consistent styling */
.maljani-plugin-container .maljani-notice { }
.maljani-plugin-container .maljani-notice.success { }
.maljani-plugin-container .maljani-notice.error { }
.maljani-plugin-container .maljani-notice.warning { }
.maljani-plugin-container .maljani-notice.info { }
```

### Style Isolation Features
- **🛡️ Complete CSS Reset** - Plugin styles protected from theme interference
- **🎨 Consistent Design** - Same appearance across all WordPress themes  
- **📱 Responsive Layout** - Mobile-optimized with theme independence
- **⚡ Performance Optimized** - Critical CSS inline, non-critical loaded async

## Troubleshooting

❌ **Shortcode not displaying?**
- Check plugin is activated
- Verify page contains shortcode
- Check user permissions

❌ **Premium not calculating?**
- Verify policy has price ranges configured
- Check date inputs are valid
- Review policy meta fields

❌ **PDF not generating?**
- Check file permissions
- Verify TCPDF library is loaded
- Test with simple policy data

## Support Resources

- **Full Documentation:** `SHORTCODES.md`
- **Diagnostic Tool:** WordPress Admin → Maljani → Diagnostic
- **GitHub:** https://github.com/kanji8210/maljani_travel_insurance_hub
