# Maljani Travel Insurance Hub - Shortcodes Quick Guide

## Available Shortcodes

### Policy Filter & Display

#### `[maljani_policy_ajax_filter]`
Complete policy search with AJAX filtering
```
[maljani_policy_ajax_filter]
```

#### `[maljani_filter_form]`
Filter form only (redirects to results page)
```
[maljani_filter_form redirect="/policies/"]
```

#### `[maljani_policy_grid]`
Customizable policy grid
```
[maljani_policy_grid columns="3" posts_per_page="12"]
```

**Parameters:**
- `columns` - Number of columns (1-4, default: 3)
- `posts_per_page` - Policies to show (1-50, default: 12)
- `region` - Pre-filter by region ID (optional)

**Examples:**
```
<!-- 4-column grid, 20 policies -->
[maljani_policy_grid columns="4" posts_per_page="20"]

<!-- List view -->
[maljani_policy_grid columns="1" posts_per_page="30"]
```

---

### Sales & Registration

#### `[maljani_policy_sale]`
Complete policy purchase form
```
[maljani_policy_sale]
```

#### `[maljani_insured_register]`
Customer registration form
```
[maljani_insured_register]
```

---

### User Dashboard

#### `[maljani_user_dashboard]`
User dashboard (requires login)
```
[maljani_user_dashboard]
```

---

### Utility

#### `[maljani_icon]`
Display SVG icons
```
[maljani_icon name="shield" size="24" color="#222"]
```

**Available icons:**
- shield, plane, calendar, user, check, alert, info, download

---

## Common Setups

### Homepage (Search Form Only)
```
[maljani_filter_form redirect="/policies/"]
```

### Results Page (Grid Only)
```
[maljani_policy_grid columns="3" posts_per_page="15"]
```

### All-in-One Search
```
[maljani_policy_ajax_filter]
```

### Purchase Page
```
[maljani_policy_sale]
```

### Customer Portal
```
[maljani_user_dashboard]
```

---

**For full documentation, see:** [SHORTCODES-REFERENCE.md](SHORTCODES-REFERENCE.md)
