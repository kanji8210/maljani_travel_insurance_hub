# Maljani Travel Insurance Hub - Shortcodes Reference

Complete documentation for all available shortcodes in the Maljani Travel Insurance Hub plugin.

---

## Table of Contents

1. [Policy Filter & Display Shortcodes](#policy-filter--display-shortcodes)
2. [Sales & Registration Shortcodes](#sales--registration-shortcodes)
3. [User Dashboard Shortcodes](#user-dashboard-shortcodes)
4. [Utility Shortcodes](#utility-shortcodes)

---

## Policy Filter & Display Shortcodes

### `[maljani_policy_ajax_filter]`

**Description:** Displays a complete policy search interface with AJAX filtering. Includes date inputs, region filter buttons, and policy results grid.

**Parameters:** None

**Usage:**
```
[maljani_policy_ajax_filter]
```

**Features:**
- Date range picker (departure/return dates)
- Region filter buttons (AJAX-powered, no page reload)
- Live policy grid updates
- Automatic travel duration calculation
- Responsive 3-column grid (mobile-friendly)

**Best For:** Main policy search pages where users need to filter and browse policies in one place.

---

### `[maljani_filter_form]`

**Description:** Displays ONLY the filter form without the policy grid. Submits to a results page via GET parameters.

**Parameters:**
- `redirect` - URL to redirect to with filter parameters (optional)

**Usage:**
```
<!-- Basic form that submits to current page -->
[maljani_filter_form]

<!-- Form that redirects to specific results page -->
[maljani_filter_form redirect="/policy-results/"]
```

**GET Parameters Sent:**
- `departure` - Departure date (YYYY-MM-DD)
- `return` - Return date (YYYY-MM-DD)
- `region_id` - Selected region term ID (empty = all regions)

**Features:**
- Lightweight form-only display
- Standard form submission (no AJAX)
- Region dropdown (instead of buttons)
- Redirects to results page with query parameters

**Best For:** 
- Homepage search widgets
- Sidebar search forms
- Multi-step search flows where form and results are on different pages

**Example Multi-Page Setup:**
```
<!-- On homepage (search form only) -->
[maljani_filter_form redirect="/policies/"]

<!-- On /policies/ page (results grid only) -->
[maljani_policy_grid columns="4" posts_per_page="16"]
```

---

### `[maljani_policy_grid]`

**Description:** Displays a customizable grid of policies. Can read filter parameters from URL (works with `maljani_filter_form`) or display all policies.

**Parameters:**
- `columns` - Number of columns (1-4, default: 3)
- `posts_per_page` - Number of policies to display (1-50, default: 12)
- `region` - Pre-filter by region term ID (optional)

**Usage:**
```
<!-- Default 3-column grid, 12 policies -->
[maljani_policy_grid]

<!-- 4-column grid with 20 policies -->
[maljani_policy_grid columns="4" posts_per_page="20"]

<!-- 2-column grid showing only Europe policies -->
[maljani_policy_grid columns="2" region="5"]

<!-- Single column (list view) with 30 policies -->
[maljani_policy_grid columns="1" posts_per_page="30"]
```

**Features:**
- Customizable grid layout (1-4 columns)
- Adjustable posts per page
- Reads URL parameters from `maljani_filter_form`
- Responsive design (auto-adjusts on mobile)
- Shows travel duration if dates provided

**Responsive Behavior:**
- Desktop: Uses specified column count
- Tablet (< 1024px): Maximum 2 columns
- Mobile (< 700px): Always 1 column

**Best For:**
- Custom policy archive pages
- Category-specific policy displays
- Results pages paired with `maljani_filter_form`

---

## Sales & Registration Shortcodes

### `[maljani_policy_sale]`

**Description:** Complete policy purchase form with PDF generation. The main sales interface for customers.

**Parameters:** None

**Usage:**
```
[maljani_policy_sale]
```

**Features:**
- Policy selection dropdown
- Travel dates with duration calculation
- Automatic premium calculation (AJAX)
- Customer details form
- PDF certificate generation
- Email notifications
- Mobile Money payment integration

**Form Fields:**
- Policy selection (dropdown)
- Departure/return dates
- Customer name, email, phone
- National ID
- Emergency contact
- Gender selection
- Payment method (Mobile Money)

**Best For:** Main checkout/purchase page for policy sales.

---

### `[maljani_sales_form]`

**Description:** Alias for `[maljani_policy_sale]` - maintained for backward compatibility.

**Parameters:** None

**Usage:**
```
[maljani_sales_form]
```

**Note:** Use `[maljani_policy_sale]` for new implementations. This shortcode may be deprecated in future versions.

---

### `[maljani_insured_register]`

**Description:** Registration form for new insured customers.

**Parameters:** None

**Usage:**
```
[maljani_insured_register]
```

**Features:**
- Customer registration form
- User account creation
- WordPress user integration
- Email verification

**Form Fields:**
- Full name
- Email address
- Phone number
- Password

**Best For:** Customer registration pages, member sign-up flows.

---

## User Dashboard Shortcodes

### `[maljani_user_dashboard]`

**Description:** Complete user dashboard for logged-in customers. Shows purchased policies, profile, and account management.

**Parameters:** None

**Usage:**
```
[maljani_user_dashboard]
```

**Features:**
- User authentication (login required)
- Purchase history
- Active policies display
- Policy certificates download
- Profile management
- Password change
- Logout functionality

**Sections:**
- My Policies (list of purchased policies)
- Profile Information
- Account Settings
- Support/Help links

**Access Control:** Requires user to be logged in. Redirects to login page if not authenticated.

**Best For:** Member area, customer portal, account management pages.

---

## Utility Shortcodes

### `[maljani_icon]`

**Description:** Displays SVG icons from the plugin's icon library.

**Parameters:**
- `name` - Icon name (required)
- `size` - Icon size in pixels (optional, default: 24)
- `color` - Icon color (optional, default: currentColor)

**Available Icons:**
- `shield` - Security/protection icon
- `plane` - Travel/flight icon
- `calendar` - Date/schedule icon
- `user` - Profile/account icon
- `check` - Success/confirmation icon
- `alert` - Warning/notice icon
- `info` - Information icon
- `download` - Download/PDF icon

**Usage:**
```
<!-- Basic icon -->
[maljani_icon name="shield"]

<!-- Custom sized icon -->
[maljani_icon name="plane" size="32"]

<!-- Custom colored icon -->
[maljani_icon name="check" color="#1e5c3a"]

<!-- Large custom icon -->
[maljani_icon name="alert" size="48" color="#c00"]
```

**Best For:**
- Enhancing text content with visual elements
- Feature lists
- Benefits sections
- Call-to-action buttons

---

## Common Use Cases & Page Setups

### Homepage Setup
```
<h1>Find Your Perfect Travel Insurance</h1>
<p>Search from our comprehensive policy database</p>

[maljani_filter_form redirect="/policies/"]
```

### Policy Results Page
```
<h1>Available Policies</h1>

[maljani_policy_grid columns="3" posts_per_page="15"]
```

### All-in-One Search Page
```
<h1>Browse Travel Insurance Policies</h1>

[maljani_policy_ajax_filter]
```

### Purchase Page
```
<h1>Purchase Your Policy</h1>

[maljani_policy_sale]
```

### Customer Portal
```
<h1>My Account</h1>

[maljani_user_dashboard]
```

### Registration Page
```
<h1>Create Your Account</h1>
<p>Register to purchase and manage your policies</p>

[maljani_insured_register]
```

### Features Page with Icons
```
<h2>Why Choose Us?</h2>

<div class="features">
    <div class="feature">
        [maljani_icon name="shield" size="48"]
        <h3>Comprehensive Coverage</h3>
        <p>Full protection for your travels</p>
    </div>
    
    <div class="feature">
        [maljani_icon name="plane" size="48"]
        <h3>Global Travel</h3>
        <p>Coverage in 150+ countries</p>
    </div>
    
    <div class="feature">
        [maljani_icon name="check" size="48"]
        <h3>Easy Claims</h3>
        <p>Fast and simple claim process</p>
    </div>
</div>
```

---

## Filter Parameters Reference

When using `[maljani_filter_form]` with `[maljani_policy_grid]`, these URL parameters are passed:

| Parameter | Type | Format | Description |
|-----------|------|--------|-------------|
| `departure` | Date | YYYY-MM-DD | Travel departure date |
| `return` | Date | YYYY-MM-DD | Travel return date |
| `region_id` | Integer | 1, 2, 3... | Policy region term ID |

**Example URL:**
```
/policies/?departure=2024-06-15&return=2024-06-30&region_id=3
```

---

## Performance Considerations

### Caching
- Policy grids use transient caching (1 hour)
- Premium calculations cached for 24 hours
- Region taxonomy queries cached

### Optimization Tips
1. Use `posts_per_page` wisely (12-20 recommended)
2. Limit columns to 3-4 for better performance
3. Combine `maljani_filter_form` + `maljani_policy_grid` on separate pages for faster load times
4. Use `region` parameter to pre-filter when possible

---

## Styling Notes

All shortcodes use minimalist inline styles with:
- Text color: `#222` (dark gray)
- Link color: `#1e5c3a` (dark green)
- Border color: `#ddd` (light gray)
- No background colors
- No border-radius
- No box-shadows

**Custom CSS Classes:**
- `.maljani-filter-wrapper` - Main filter container
- `.maljani-policy-grid-{N}` - Policy grid (N = column count)
- `.maljani-filter-form-only` - Standalone filter form
- `.region-filter-btn` - Region filter buttons
- `.policy-item` - Individual policy card

---

## Browser Compatibility

All shortcodes are compatible with:
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

**Features Used:**
- CSS Grid (all modern browsers)
- Fetch API for AJAX (polyfill may be needed for IE11)
- Date input type (graceful degradation for older browsers)

---

## Troubleshooting

### Filter not working
- Check that AJAX endpoints are enabled
- Verify nonce generation (check browser console)
- Ensure jQuery is loaded

### No policies showing
- Verify policies are published
- Check region taxonomy assignments
- Review posts_per_page limits

### PDF generation fails
- Check TCPDF library installation
- Verify uploads directory permissions (wp-content/uploads/)
- Review PHP memory limit (128MB+ recommended)

### Icons not displaying
- Confirm icon name is correct
- Check SVG support in theme
- Verify currentColor CSS variable support

---

## Version History

- **v1.0.1** (Current)
  - Added `[maljani_filter_form]` shortcode
  - Added `[maljani_policy_grid]` with columns/posts_per_page parameters
  - Enhanced responsive design
  - Improved performance caching

- **v1.0.0**
  - Initial release
  - Basic shortcodes: `maljani_policy_ajax_filter`, `maljani_policy_sale`, `maljani_user_dashboard`

---

## Support & Documentation

For additional help:
- Main Documentation: `DOCUMENTATION.md`
- Setup Guide: `SETUP-GUIDE.md`
- Quick Reference: `QUICK-REFERENCE.md`
- Changelog: `CHANGELOG.md`

---

*Last updated: 2024*
