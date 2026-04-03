# Maljani Frontend Development Guide

This document serves as the primary reference for the React/Next.js frontend development.

## Core Flow Architecture

### 1. Landing Page & Hero
- **Objective**: "Destination-First" discovery.
- **Regions**: Dynamically fetched from Maljani WordPress backend.
- **Selection**: Interactive chips for popular destinations + searchable input.

### 2. Policy Showcase (Elite Coverage)
- **Selection**: 9 randomized plans on load.
- **Components**:
  - **Thumbnail**: Policy featured image.
  - **Insurer Tag**: Logo + Name (with popup Profile Modal).
  - **View Benefits**: Dynamic specification modal based on policy ID/Title.
  - **View Policy**: Internal navigation to the full specification page.

### 3. Policy Detail View
- **Internal Only**: No external redirection.
- **Components**: full legal content, specification grids, exclusion sections.
- **CTA**: Persistent "Get Quote" link leading to the Quote Wizard.

### 4. Quote Wizard (Kiptach Flow)
- **Step 1**: Personal Details/Origin.
- **Step 2**: Plan Selection.
- **Step 3**: Configuration & Verification.
- **Step 4**: Secure Transaction & Confirmation.

## Technical Standards
- **Framework**: React (Next.js context).
- **Data**: GraphQL via `urql`.
- **Styling**: Vanilla CSS with glassmorphism and Maljani gold theme.
- **Routing**: Internal state-based routing in `App.jsx` for a seamless SPA experience.
