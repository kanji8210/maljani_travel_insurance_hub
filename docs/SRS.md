# ⚙️ Software Requirements Specification (SRS) - Maljani Hub

## 1. Introduction
This document outlines the technical specifications, architecture, and data models of the Maljani Travel Insurance Hub.

## 2. System Architecture
### 🧩 WordPress Integration
The system is built as a WordPress plugin (`maljani.php`) and utilizes:
- **Custom Post Types (CPTs)**:
  - `policy`: Stores insurance plans and premium schedules.
  - `insurer_profile`: Manage insurer contact and API credentials.
- **Custom Taxonomies**:
  - `policy_region`: Filter policies by geographical coverage (e.g., Schengen, Worldwide).

### 🗄️ Database Schema
The plugin creates custom tables for high-performance sales tracking:
- **`wp_policy_sale`**: Stores every purchase, including insured details, pricing, and status.
- **`wp_maljani_agencies`**: Managed list of travel agencies with commission records.

## 3. Core Modules
### 💸 Premium Calculation Engine
The engine calculates premiums based on:
1.  **Duration**: Difference between departure and return dates.
2.  **Rate Table**: Stored in post meta `_policy_day_premiums` (array of from/to/premium).
3.  **Global Fees**: Configurable service fees (fixed or percent).

### 🛠️ Insurer API Engine
Modular system in `includes/api/` and `includes/adapters/`:
- **Base Adapter**: Provides common methods for policy registration.
- **Custom Adapters**: Specific logic for each insurer backend.

### 🔌 GraphQL (Headless)
Enabled via WPGraphQL and the custom `Maljani_GraphQL_Auth` class:
- **JWT Auth**: Custom HMAC-SHA256 signing for stateless mobile access.
- **Mutations**: `loginUser`, `registerUser`, `submitPolicySale`.

## 4. Security & Compliance
- **Shortcode Isolation**: CSS is isolated via `.maljani-plugin-container` and a reset layer.
- **Data Protection**: Sanitization of all user inputs using WordPress core functions.
- **Authentication**: JWT tokens for headless requests, standard WP roles for portal access.

## 5. UI/UX Specifications
- **Framework**: Vanilla CSS with custom design tokens.
- **Typography**: Inter / Outfit (Google Fonts).
- **Interactive**: Ajax-driven Quote Wizard and Sales forms.

---
*Status: Final Draft | Version: 1.0.1 | Architect: Antigravity*
