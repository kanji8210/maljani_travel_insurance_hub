# 📝 Product Requirements Document (PRD) - Maljani Travel Insurance Hub

## 1. Executive Summary
Maljani Travel Insurance Hub is a comprehensive WordPress-based platform designed to aggregate travel insurance policies from multiple insurers and provide a unified portal for Agencies and individual Travelers to purchase coverage. It features dynamic premium calculation, secure document generation, and a headless-ready architecture.

## 2. Target Audience & User Roles
| Role | Description | Key Needs |
|------|-------------|-----------|
| **Administrator** | Maljani Core Team | Manage insurers, verify sales, and configure global settings. |
| **Agent** | Travel Agencies | Purchase policies on behalf of clients, track commissions, and manage client history. |
| **Traveler (Insured)** | Individual Clients | Self-register, purchase direct coverage, and download travel certificates. |
| **Insurer** | Insurance Companies | Automated notification of sales (via API adapters). |

## 3. Core Features
### 🛒 Unified Sales Engine
- **Multi-step Quote Wizard**: User-friendly interface for finding the right policy.
- **Dynamic Premiums**: Calculation based on travel duration, region, and insurer-specific rates.
- **Secure Checkout**: Integration with payment gateways (e.g., Pesapal).

### 👥 Portal Management
- **Unified Registration**: One entry point for both Agents and Clients.
- **Dedicated Dashboards**: Access to past policies, commission statements (for agents), and profile settings.

### 📄 Automated Document Generation
- **Policy Certificates**: Instant PDF generation upon purchase verification.
- **Embassy Letters**: QR-coded verification letters for visa applications.

### 🔌 Connectivity
- **Insurer API Engine**: Modular adapter system to sync sales with different insurance backends.
- **Headless GraphQL**: Full API support for external mobile or web aggregators.

## 4. Design Guidelines
- **Aesthetic**: Premium, minimalist, and "Glassmorphic".
- **UX**: Fast, mobile-first, and high accessibility.
- **Consistency**: Centralized CSS tokens for unified branding across all shortcodes.

## 5. Success Metrics
- Conversion rate from quote to purchase.
- Reduced manual effort in document issuance via automation.
- Seamless integration of new insurer partners.

---
*Status: Draft | Version: 1.0.0 | Contact: [KipDev](https://kipdevwp.tech/)*
