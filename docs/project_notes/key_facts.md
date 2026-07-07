# Key Facts

Log project configuration, constants, environment details, and important URLs here.

## Project Details
- **Plugin Name**: Travel Insurance Center-Kenya (plugin slug: maljani_travel_insurance_hub)
- **Version**: 1.0.1
- **Main Plugin File**: `maljani.php`

## Environment
- **Server**: XAMPP (Windows)
- **Root**: `c:\xampp\htdocs\wordpress`
- **Plugin Path**: `wp-content\plugins\maljani_travel_insurance_hub`

## Core Architecture
- **API Engine**: Insurer API integration via adapters.
- **Frontend**: WordPress templates with modern CSS (glassmorphism).
- **Automation**: Agentic AI skills in `.agents/skills/`.
- **Default Exchange Rate Option**: `maljani_default_usd_to_ksh_rate` stores the global USD -> KSH rate. When set, USD policy premiums are displayed/calculated in KSH using this rate; insurer `_insurer_usd_to_ksh_rate` is only used if the global default is not set.

## Important URLs
- **Admin Dashboard**: `/wp-admin/`
- **Sales Form**: (Depends on shortcode placement)
