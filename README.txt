=== Maljani Travel Insurance Hub ===
Contributors: denniskip
Donate link: https://kipdevwp.tech/
Tags: insurance, travel, policy management, sales, pdf generation
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive WordPress plugin for managing travel insurance policies, insurers, and sales with PDF generation and user dashboards.

== Description ==

Maljani Travel Insurance Hub is a modular WordPress plugin designed to streamline the management of travel insurance within a centralized admin interface. It enables administrators to register, display, and manage insurers and insurance policies with complete CRUD operations, role-based permissions, and frontend submissions.

= Key Features =

* **Policy Management**: Create and manage travel insurance policies with custom post types
* **Insurer Profiles**: Dedicated profiles for insurance providers with logos and details
* **Sales System**: Complete sales workflow with 4-step process (dates, region, policy, details)
* **PDF Generation**: Professional PDF certificates and embassy letters with QR code verification
* **User Roles**: Custom roles for agents and insured persons
* **User Dashboards**: Personalized dashboards for agents and customers
* **Premium Calculations**: Automatic premium calculation based on travel duration
* **Regional Filtering**: Filter policies by destination regions
* **Security**: SHA256 verification for document authenticity
* **Performance**: Built-in caching system for optimal speed
* **Logging**: Comprehensive error logging for debugging

= Available Shortcodes =

* `[maljani_policy_sale]` - Main insurance sales form
* `[maljani_user_dashboard]` - User dashboard for viewing policies
* `[maljani_agent_register]` - Agent registration form
* `[maljani_icon]` - Icon display system

For complete shortcode documentation, see SHORTCODES.md in the plugin directory.

= Technical Highlights =

* Built on WordPress plugin boilerplate architecture
* TCPDF integration for professional PDF generation
* Custom taxonomies for policy regions
* AJAX-powered premium calculations
* Style isolation system for theme compatibility
* Responsive design for all screen sizes
* Translation-ready with i18n support

== Installation ==

1. Upload the `maljani_travel_insurance_hub` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Maljani to configure the plugin
4. Create pages for sales form and user dashboard, insert shortcodes
5. Add insurers under Insurer Profiles menu
6. Create insurance policies under Policies menu
7. Assign regions to policies using the Policy Regions taxonomy

= First Time Setup =

1. Configure payment details in Settings > Maljani
2. Set terms and conditions for policies
3. Create at least one insurer profile
4. Create at least one policy region (e.g., Europe, Asia, Worldwide)
5. Create policies and assign them to insurers and regions
6. Set premium rates based on travel duration

== Frequently Asked Questions ==

= How do I add a new insurance policy? =

Go to Policies > Add New in your WordPress admin. Fill in the policy details, assign an insurer, select applicable regions, and set premium rates based on travel duration.

= Can I customize the PDF output? =

Yes, the PDF templates are located in `includes/generate-policy-pdf.php`. You can modify the HTML and CSS to match your branding.

= How does the sales process work? =

1. Customer selects travel dates
2. Customer chooses destination region
3. System shows available policies for that region
4. Customer completes purchase form
5. System generates PDF certificate
6. Customer receives email with login details

= What user roles does the plugin create? =

The plugin creates two custom roles:
- **Agent**: Can sell policies and view their sales
- **Insured**: Customers who purchased policies

= Is the plugin translation-ready? =

Yes, all user-facing strings are wrapped in translation functions. POT file is included in the `/languages` directory.

= How secure are the generated PDFs? =

PDFs include SHA256 hash verification and unique QR codes for authenticity checking. Access is restricted to authorized users only.

== Screenshots ==

1. Admin dashboard showing policy management
2. Insurance sales form (4-step process)
3. User dashboard with policy listings
4. PDF certificate sample
5. Policy premium configuration
6. Insurer profile management

== Changelog ==

= 1.0.1 - 2026-01-26 =
* Security: Added CSRF protection to AJAX endpoints
* Security: Enabled permission checks for PDF generation
* Security: Added comprehensive input validation
* Performance: Implemented caching system for premium calculations
* Performance: Added object caching for policy queries
* Feature: Added structured logging system
* Feature: Automatic cache cleanup on policy updates
* Improvement: Better error handling and reporting
* Improvement: Enhanced date validation
* Fix: Prevented unauthorized PDF access

= 1.0.0 - 2025-07-28 =
* Initial release
* Complete sales workflow system
* PDF generation with TCPDF
* User dashboard and agent registration
* Custom post types for policies and insurers
* Regional policy filtering
* Premium calculation engine
* QR code verification system
* Style isolation for theme compatibility

== Upgrade Notice ==

= 1.0.1 =
Important security update! This version adds CSRF protection, permission checks, and input validation. Upgrade immediately for better security and performance.

= 1.0.0 =
Initial release of Maljani Travel Insurance Hub.

== Additional Information ==

= Support =

For support and documentation:
* Website: https://kipdevwp.tech/
* GitHub: https://github.com/kanji8210/maljani_travel_insurance_hub
* Email: denisdekemet@gmail.com

= Contributing =

Contributions are welcome! Please submit pull requests on GitHub.

= Credits =

* TCPDF Library for PDF generation
* WordPress Plugin Boilerplate as foundation
* Icons from WordPress Dashicons

= Privacy Policy =

This plugin stores customer information including names, emails, passport numbers, and travel dates in the WordPress database. Ensure compliance with GDPR and local data protection laws. PDFs generated may contain sensitive personal information.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`