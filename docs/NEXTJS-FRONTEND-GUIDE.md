# Maljani Travel Insurance Hub — Next.js Frontend Guide

> **Purpose**: This document is the single source of truth for any AI agent or developer building the Next.js frontend for Maljani Hub. The WordPress backend remains unchanged; this frontend communicates through WPGraphQL and WP REST API endpoints. Every section in this document has been verified against the actual plugin PHP source code.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Environment Setup](#2-environment-setup)
3. [Authentication Strategy](#3-authentication-strategy)
4. [Data Models](#4-data-models)
5. [GraphQL Queries & Mutations — Full Reference](#5-graphql-queries--mutations--full-reference)
6. [REST API Endpoints — Full Reference](#6-rest-api-endpoints--full-reference)
7. [Policy Thumbnail — Complete Data Retrieval Guide](#7-policy-thumbnail--complete-data-retrieval-guide)
8. [Payment Flow — Pesapal v3](#8-payment-flow--pesapal-v3)
9. [PDF Certificate Download](#9-pdf-certificate-download)
10. [Policy Verification](#10-policy-verification)
11. [Pages Required](#11-pages-required)
12. [Routing Map](#12-routing-map)
13. [State Management Notes](#13-state-management-notes)
14. [UI/Design Conventions](#14-uidesign-conventions)
15. [What the WP Backend Still Needs](#15-what-the-wp-backend-still-needs)

---

## 1. Architecture Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                       Next.js App (Headless)                     │
│                                                                  │
│  ┌─────────────────────┐   ┌──────────────────────────────────┐  │
│  │   GraphQL Client    │   │       REST API (fetch)           │  │
│  │  (Apollo / fetch)   │   │  maljani-crm/v1  maljani-chat/v1 │  │
│  └──────────┬──────────┘   └────────────┬─────────────────────┘  │
└─────────────┼────────────────────────────┼──────────────────┬────┘
              │ POST /graphql              │ /wp-json/…       │
              │ X-Maljani-App-Secret       │ Authorization     │ direct URL
              ▼                           ▼                   ▼
┌─────────────────────────────────────────────────────────────────┐
│                  WordPress (WP Backend)                         │
│  WPGraphQL  ─►  Maljani_GraphQL_Auth  (3 mutations registered) │
│  REST API   ─►  maljani-crm/v1  (policies, clients, payments)  │
│  REST API   ─►  maljani/v1  (pesapal callback)                 │
│  REST API   ─►  maljani-chat/v1  (live chat)                   │
│  Direct PHP ─►  generate-policy-pdf.php  (PDF certificate)     │
│  WP rewrite ─►  /verify-policy/  (public verification)         │
│                                                                 │
│  CPTs: policy, insurer_profile                                  │
│  Taxonomy: policy_region                                        │
│  DB: wp_policy_sale, wp_maljani_agencies                        │
└─────────────────────────────────────────────────────────────────┘
```

**Key points:**
- Public data (policies, regions) flows through `POST /graphql`.
- User-specific data (sales, commissions) uses the `maljani-crm/v1` REST namespace.
- Every GraphQL request must include `X-Maljani-App-Secret`.
- User-protected mutations additionally require `Authorization: Bearer <jwt>`.
- CORS is pre-configured on the WP side; `localhost:5173` and `localhost:5174` are whitelisted by default.

> **IMPORTANT**: Several policy post-meta fields (`_policy_description`, `_policy_benefits`, etc.) are **not yet registered as GraphQL fields**. Until those are added to the WP plugin, fetch them from the WPGraphQL `extraFields` workaround or use a custom REST endpoint. See [Section 15](#15-what-the-wp-backend-still-needs) for the exact PHP code to add.

---

## 2. Environment Setup

Create a `.env.local` in the Next.js project root:

```env
# GraphQL endpoint (no trailing slash)
NEXT_PUBLIC_GRAPHQL_URL=https://your-wp-site.com/graphql

# Shared application secret — matches WP option: maljani_graphql_app_secret
# Configured in WP Admin > Maljani > Settings > GraphQL & App Security
NEXT_PUBLIC_APP_SECRET=your_app_secret_here

# REST API base (same WP domain)
NEXT_PUBLIC_WP_API_URL=https://your-wp-site.com/wp-json
```

> **Security**: Never store the JWT in `localStorage`. Use an `httpOnly` cookie set via a Next.js API Route (`/api/auth/login`).

### Recommended GraphQL client

```bash
npm install @apollo/client graphql
npm install lucide-react          # icons (matches WP admin side)
npm install nookies               # httpOnly cookie helpers for Next.js
```

### Apollo Client setup (`lib/apolloClient.ts`)

```typescript
import { ApolloClient, InMemoryCache, createHttpLink } from "@apollo/client";
import { setContext } from "@apollo/client/link/context";
import { parseCookies } from "nookies";

const httpLink = createHttpLink({
  uri: process.env.NEXT_PUBLIC_GRAPHQL_URL,
});

const authLink = setContext((_, { headers }) => {
  const cookies = parseCookies();
  const token = cookies["maljani_token"] ?? "";

  return {
    headers: {
      ...headers,
      "X-Maljani-App-Secret": process.env.NEXT_PUBLIC_APP_SECRET ?? "",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  };
});

export const apolloClient = new ApolloClient({
  link: authLink.concat(httpLink),
  cache: new InMemoryCache(),
});
```

### REST API helper (`lib/api.ts`)

```typescript
import { parseCookies } from "nookies";

export async function wpFetch<T>(path: string, options?: RequestInit): Promise<T> {
  const cookies = parseCookies();
  const token = cookies["maljani_token"] ?? "";

  const res = await fetch(`${process.env.NEXT_PUBLIC_WP_API_URL}${path}`, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options?.headers ?? {}),
    },
  });
  if (!res.ok) throw new Error(`API error ${res.status}`);
  return res.json();
}
```

---

## 3. Authentication Strategy

### User Roles (defined in WordPress)
| Role | Description | Frontend Access |
|------|-------------|-----------------|
| `insured` | Individual traveler | Own policies, certificate download, profile |
| `agent` | Travel agency staff | All sold policies, commission statement, disputes |
| `administrator` | Maljani team | WP Admin only — not exposed in frontend |

### Login Flow

1. User submits email + password on `/auth/login`.
2. Frontend calls `maljaniLogin` mutation (verified in source: `class-maljani-graphql-auth.php`).
3. On success, store `authToken` in an `httpOnly` cookie via a Next.js API route `/api/auth/login`.
4. Read `roles.nodes[0].name` from the `user` object returned by the mutation.
5. Redirect: `agent` → `/dashboard/agent`, `insured` → `/dashboard/client`.

### Next.js API Route (`pages/api/auth/login.ts`)

```typescript
import { serialize } from "cookie";
import type { NextApiRequest, NextApiResponse } from "next";

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  const { token, role } = req.body;
  res.setHeader("Set-Cookie", serialize("maljani_token", token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    maxAge: 60 * 60 * 24, // 24 h (matches WP JWT expiry)
    path: "/",
  }));
  res.json({ ok: true, role });
}
```

### Token Shape
The JWT is signed with HMAC-SHA256 (matches `class-maljani-graphql-auth.php::generate_token()`). Payload:
```json
{
  "sub": 42,
  "iat": 1710000000,
  "exp": 1710086400
}
```
Decode client-side with `jose` or `jwt-decode` to get `sub` (WP user ID). Do **not** trust the decoded role from the token; use the role returned by the mutation's `user.roles` field instead.

### Brute Force Protection
The WP backend blocks an IP for 1 hour after exceeding `maljani_security_max_login_retries` (default: 5) failed attempts. The frontend should display a clear error when it receives `"Your IP has been temporarily blocked."` from the mutation.

---

## 4. Data Models

### 4.1 Policy CPT (`policy`) — source: `admin/class-maljani_policy-cpt.php`

| Field | WP Post Meta Key | GraphQL Status | Notes |
|-------|-----------------|----------------|-------|
| Title (plan name) | post title | ✅ Native via WPGraphQL | `title` |
| Excerpt / Tagline | post excerpt | ✅ Native | `excerpt` |
| Feature Image | `_policy_feature_img` (attachment ID) | ✅ Native via `featuredImage` | Stored as an attachment ID in post meta; WPGraphQL returns it via the standard `featuredImage` field |
| Description | `_policy_description` | ❌ Not registered | Needs `register_graphql_field` — see Section 15 |
| Cover Details | `_policy_cover_details` | ❌ Not registered | Needs `register_graphql_field` |
| Benefits | `_policy_benefits` | ❌ Not registered | Needs `register_graphql_field` |
| Not Covered | `_policy_not_covered` | ❌ Not registered | Needs `register_graphql_field` |
| Currency | `_policy_currency` (default `KSH`) | ❌ Not registered | Needs `register_graphql_field` |
| Premium Table | `_policy_day_premiums` (array of `{from,to,premium}`) | ❌ Not registered | Needs `register_graphql_field` — **critical for pricing** |
| Payment Details | `_policy_payment_details` | ❌ Not registered | Needs `register_graphql_field` |
| Insurer | `_policy_insurer` (post ID of `insurer_profile`) | ❌ Not registered | Needs `register_graphql_field` |
| Regions | Taxonomy `policy_region` | ✅ Registered (`show_in_graphql: true`) | `policyRegions { nodes { name slug } }` |

### 4.2 Policy Sale (DB: `wp_policy_sale`) — source: `includes/class-maljani-graphql-auth.php`

> Returned by the `submitPolicySale` mutation and the `maljani-crm/v1/policies` REST endpoint.

| DB Column | Description |
|-----------|-------------|
| `id` | Sale ID |
| `policy_number` | Auto-generated (e.g. `MAL-1234`) |
| `policy_id` | Related policy CPT post ID |
| `insured_names` | Full name |
| `insured_email` | Email |
| `insured_phone` | Phone |
| `insured_dob` | Date of birth |
| `passport_number` | — |
| `national_id` | — |
| `insured_address` | — |
| `country_of_origin` | — |
| `departure` | `YYYY-MM-DD` |
| `return` (column: `return`) | `YYYY-MM-DD` |
| `days` | Calculated duration |
| `region` | Region name string |
| `premium` | Net insurer premium |
| `maljani_comm_amount` | Platform commission |
| `agent_commission_amount` | Agent cut |
| `amount_paid` | Total charged to client |
| `currency` | e.g. `KSH` |
| `policy_status` | `pending`, `verified`, `active`, `rejected` |
| `payment_status` | `pending`, `confirmed` |
| `payment_reference` | Pesapal tracking ID or manual ref |
| `agent_id` | WP user ID (0 if direct sale) |
| `agent_commission_status` | `pending`, `paid`, `received`, `disputed` |

### 4.3 Region (Taxonomy: `policy_region`) — registered in GraphQL

| Field | GraphQL Key |
|-------|-------------|
| Name | `name` |
| Slug | `slug` |
| Term ID | `termTaxonomyId` |
| Count | `count` |

---

## 5. GraphQL Queries & Mutations — Full Reference

> **Always include both headers on every request:**
> ```
> X-Maljani-App-Secret: <value from maljani_graphql_app_secret WP option>
> Content-Type: application/json
> Authorization: Bearer <jwt>   ← only on authenticated calls
> ```

> **Status key**: ✅ = works today | ⚠️ = works once Section 15 PHP is added to WP

---

### 5.1 Get All Regions ✅

```graphql
query GetRegions {
  policyRegions(first: 50) {
    nodes {
      id
      name
      slug
      termTaxonomyId
      count
    }
  }
}
```

---

### 5.2 Get All Policies — Catalog Listing ⚠️

> The `policyDayPremiums`, `policyCurrency`, `policyDescription` fields require the PHP from Section 15 to be added first.

```graphql
query GetPolicies($first: Int = 12, $after: String, $region: String) {
  policies(
    first: $first
    after: $after
    where: {
      taxQuery: {
        taxArray: [{ taxonomy: POLICY_REGION, field: SLUG, terms: [$region] }]
      }
    }
  ) {
    pageInfo {
      hasNextPage
      endCursor
    }
    nodes {
      id
      databaseId
      title
      excerpt
      slug
      featuredImage {
        node {
          sourceUrl(size: MEDIUM)
          altText
        }
      }
      policyRegions {
        nodes { name slug }
      }
      # ⚠️ These fields need Section 15 registration:
      policyDayPremiums { from to premium }
      policyCurrency
      policyDescription
    }
  }
}
```

> **Workaround until registered**: Omit the `⚠️` fields and fetch them separately via `GET /wp-json/maljani-crm/v1/policies` REST endpoint, which reads the same post meta.

---

### 5.3 Get Single Policy Detail ⚠️

```graphql
query GetPolicy($id: ID!) {
  policy(id: $id, idType: DATABASE_ID) {
    id
    databaseId
    title
    excerpt
    featuredImage {
      node { sourceUrl altText }
    }
    policyRegions {
      nodes { name slug termTaxonomyId }
    }
    # ⚠️ Needs Section 15 registration:
    policyDayPremiums { from to premium }
    policyCurrency
    policyDescription
    policyCoverDetails
    policyBenefits
    policyNotCovered
    policyPaymentDetails
  }
}
```

---

### 5.4 Login Mutation ✅

> Mutation name in WP source: `maljaniLogin`

```graphql
mutation Login($username: String!, $password: String!) {
  maljaniLogin(input: { username: $username, password: $password }) {
    authToken
    user {
      id
      databaseId
      name
      email
      roles {
        nodes { name }
      }
    }
  }
}
```

---

### 5.5 Registration Mutation ✅

> Mutation name in WP source: `maljaniRegister`

```graphql
mutation Register(
  $fullName: String!
  $email: String!
  $password: String!
  $accountType: String!   # "agent" | "insured"
  $phone: String!
  $agencyName: String     # Required only when accountType = "agent"
) {
  maljaniRegister(input: {
    fullName: $fullName
    email: $email
    password: $password
    accountType: $accountType
    phone: $phone
    agencyName: $agencyName
  }) {
    authToken
    user {
      id
      name
      roles { nodes { name } }
    }
  }
}
```

**Note:** When `accountType = "agent"`, the WP backend automatically creates a row in `wp_maljani_agencies` with `status = "pending"` and `commission_rate = 10.00`.

---

### 5.6 Submit Policy Sale (Purchase) ✅

> Requires `Authorization: Bearer <jwt>`. The WP backend calculates the premium internally using `_policy_day_premiums` — no need to pass a price from the frontend.

```graphql
mutation BuyPolicy(
  $policyId: Int!
  $departure: String!       # "YYYY-MM-DD"
  $return: String!          # "YYYY-MM-DD"
  $insuredNames: String!
  $insuredDob: String!
  $passportNumber: String
  $nationalId: String
  $insuredPhone: String!
  $insuredEmail: String!
  $insuredAddress: String
  $countryOfOrigin: String
  $paymentReference: String!
) {
  submitPolicySale(input: {
    policyId: $policyId
    departure: $departure
    return: $return
    insuredNames: $insuredNames
    insuredDob: $insuredDob
    passportNumber: $passportNumber
    nationalId: $nationalId
    insuredPhone: $insuredPhone
    insuredEmail: $insuredEmail
    insuredAddress: $insuredAddress
    countryOfOrigin: $countryOfOrigin
    paymentReference: $paymentReference
  }) {
    saleId
    policyNumber
    amountPaid
  }
}
```

**Backend behavior:**
- Calculates `days` from dates.
- Looks up matching premium bracket from `_policy_day_premiums`.
- Applies aggregator commission (`_policy_aggregator_comm_*`) and agency commission (`_policy_agency_comm_*`).
- Throws `UserError` if no premium bracket found for the given trip duration.

---

## 6. REST API Endpoints — Full Reference

> Base URL: `https://your-wp-site.com/wp-json`

### 6.1 CRM — My Policies (Agent/Insured)

> Source: `includes/class-maljani-crm.php` | Namespace: `maljani-crm/v1`
> Requires: `is_user_logged_in()` + role `agent` or `edit_maljani_policies`.

```
GET /wp-json/maljani-crm/v1/policies
Authorization: Bearer <jwt>
```

Returns the authenticated user's policies (filtered by `agent_id` for agents, or `insured_email` for insured users internally).

```
GET /wp-json/maljani-crm/v1/policies/{id}
```

Returns a single sale record.

```
PUT /wp-json/maljani-crm/v1/policies/{id}
Content-Type: application/json
{ "status": "active" }
```

Update a policy draft.

---

### 6.2 CRM — Clients

```
GET  /wp-json/maljani-crm/v1/clients
POST /wp-json/maljani-crm/v1/clients
PUT  /wp-json/maljani-crm/v1/clients/{id}
```

---

### 6.3 CRM — Commissions

```
POST /wp-json/maljani-crm/v1/commissions/{id}/dispute
POST /wp-json/maljani-crm/v1/commissions/{id}/mark-received
```

Use these for the Agent Dashboard commission actions. Mirror of the WP form-based flow in `handle_agency_commission_actions()`.

---

### 6.4 Payments

```
POST /wp-json/maljani-crm/v1/payments
GET  /wp-json/maljani-crm/v1/payments
```

---

### 6.5 Pesapal IPN (Webhook — do not call from frontend)

```
GET /wp-json/maljani/v1/pesapal/callback?OrderTrackingId=…&OrderMerchantReference=…
```

This is the IPN endpoint Pesapal calls server-to-server. Do not invoke from the frontend.

---

## 7. Policy Thumbnail — Complete Data Retrieval Guide

A **policy thumbnail** (card) on the catalog grid or quote results requires the following data points. This section explains exactly what to query and how to compute the display values.

### 7.1 What Goes on a Thumbnail Card

```
┌─────────────────────────────────┐
│  [Feature Image]                │
│  [Region Badge(s)]              │
│─────────────────────────────────│
│  [Policy Title]                 │
│  [Excerpt / Short Description]  │
│                                 │
│  Starting from                  │
│  [Calculated Price] KSH         │
│                                 │
│  Coverage: [N] days – [M] days  │
│  [Buy Now Button]               │
└─────────────────────────────────┘
```

### 7.2 Minimum GraphQL Fragment for a Thumbnail

> ⚠️ `policyDayPremiums` and `policyCurrency` require Section 15 fields to be registered first.

```graphql
fragment PolicyThumbnail on Policy {
  databaseId
  title
  excerpt(format: RAW)
  slug
  featuredImage {
    node {
      sourceUrl(size: MEDIUM)
      altText
    }
  }
  policyRegions {
    nodes {
      name
      slug
    }
  }
  policyDayPremiums {   # ⚠️ needs registration
    from
    to
    premium
  }
  policyCurrency        # ⚠️ needs registration
}
```

Use in your listing query:

```graphql
query GetPolicyThumbnails($first: Int = 12, $region: String) {
  policies(
    first: $first
    where: {
      taxQuery: {
        taxArray: [{
          taxonomy: POLICY_REGION
          field: SLUG
          terms: [$region]
        }]
      }
    }
  ) {
    nodes {
      ...PolicyThumbnail
    }
  }
}
```

### 7.3 Computing the "Starting From" Price

The `policyDayPremiums` field returns an array of price brackets (stored in post meta `_policy_day_premiums`):

```json
[
  { "from": 1,  "to": 7,   "premium": 1500 },
  { "from": 8,  "to": 15,  "premium": 2800 },
  { "from": 16, "to": 30,  "premium": 4500 },
  { "from": 31, "to": 90,  "premium": 9000 }
]
```

**Scenario A — No travel dates selected (Catalog page default):**

```typescript
function getStartingPrice(premiums: { from: number; to: number; premium: number }[]): number {
  if (!premiums || premiums.length === 0) return 0;
  return Math.min(...premiums.map((p) => Number(p.premium)));
}
```

**Scenario B — Travel dates provided (Quote Results):**

```typescript
function getPriceForDates(
  departure: string,
  returnDate: string,
  premiums: { from: number; to: number; premium: number }[]
): { price: number; days: number } | null {
  const days = Math.ceil(
    (new Date(returnDate).getTime() - new Date(departure).getTime()) / (1000 * 60 * 60 * 24)
  );
  const bracket = premiums.find((p) => days >= Number(p.from) && days <= Number(p.to));
  if (!bracket) return null;
  return { price: Number(bracket.premium), days };
}
```

> **Important**: The displayed price is the **gross premium** (what the client pays). The WP backend adds commission layers when `submitPolicySale` is called. Do not try to replicate commission math on the frontend.

### 7.4 Quote-Aware Grid

```typescript
const quotedPolicies = allPolicies
  .map((policy) => ({
    ...policy,
    quote: getPriceForDates(departure, returnDate, policy.policyDayPremiums),
  }))
  .filter((p) => p.quote !== null)
  .sort((a, b) => a.quote!.price - b.quote!.price);
```

### 7.5 Feature Image Fallback

```typescript
const imageUrl = policy.featuredImage?.node?.sourceUrl ?? "/images/policy-placeholder.png";
```

---

## 8. Payment Flow — Pesapal v3

> Source: `includes/api/class-maljani-pesapal-gateway.php` and `includes/api/class-maljani-api-endpoints.php`
> **Pesapal IS fully integrated** (v3 API, sandbox + live modes).

### Flow

```
Frontend                  WP Backend               Pesapal
   │                          │                       │
   │  1. submitPolicySale      │                       │
   │─────────────────────────►│                       │
   │  ← saleId, amountPaid     │                       │
   │                          │                       │
   │  2. initiate payment      │                       │
   │─────────────────────────►│                       │
   │  (POST /wp-json/maljani-crm/v1/payments)         │
   │  { saleId, amount, … }   │                       │
   │                          │──── OAuth token ─────►│
   │                          │◄─── token ────────────│
   │                          │──── submit order ─────►│
   │                          │◄─── redirect_url ─────│
   │◄───── redirect_url ──────│                       │
   │                          │                       │
   │  3. User pays on Pesapal hosted page             │
   │                                                  │
   │                          │◄── IPN callback ──────│
   │                          │  GET /maljani/v1/pesapal/callback
   │                          │  ?OrderTrackingId=…   │
   │                          │─► update policy_status=active
   │                          │─► trigger insurer API engine
```

### Frontend Steps

1. After `submitPolicySale` succeeds → store `saleId` and `amountPaid` in state.
2. Call `POST /wp-json/maljani-crm/v1/payments` with the sale details to initiate a Pesapal order.
3. Redirect the user to the `redirect_url` returned by WP.
4. On return to your `/buy/[policyId]/confirmation?saleId=…` page, poll or check the sale status via `GET /wp-json/maljani-crm/v1/policies/{saleId}`.
5. If `policy_status = "active"` → show success. If still `"pending"` → show "payment processing" with a retry/check link.

### Manual Payment Fallback

Some policies use manual payment (M-Pesa or bank transfer). In this case:
- Display the `policyPaymentDetails` field from the policy.
- The user sends payment manually and references their `saleId`.
- Pass a manual reference string as `paymentReference` in `submitPolicySale`.
- The admin verifies and activates the policy from the WP admin panel.

---

## 9. PDF Certificate Download

> Source: `includes/class-maljani-user-dashboard.php` lines 516, 833

PDF certificates are served via a **direct PHP file**, not a REST endpoint:

```
GET {WP_PLUGIN_URL}/maljani_travel_insurance_hub/includes/generate-policy-pdf.php?sale_id={id}
```

### Constructing the URL in Next.js

```typescript
const pdfUrl = `${process.env.NEXT_PUBLIC_WP_SITE_URL}/wp-content/plugins/maljani_travel_insurance_hub/includes/generate-policy-pdf.php?sale_id=${saleId}`;
```

Add `NEXT_PUBLIC_WP_SITE_URL=https://your-wp-site.com` to your `.env.local`.

### Implementation

Open the PDF in a new tab:

```tsx
<a href={pdfUrl} target="_blank" rel="noopener noreferrer">
  Download Certificate
</a>
```

> **Note**: The PHP file likely checks that the user is the owner of the sale or is an admin. The user must be authenticated (WP session). Since we are headless, the WP session cookie won't exist. **Request the WP developer to add a signed token mechanism** to the PDF URL (e.g., append `&token=<hmac>`) similar to how `Maljani_PDF_Generator::generate_verification_hash()` already works. Until then, this feature requires the user to be browsing on a WordPress-session-aware subdomain.

---

## 10. Policy Verification (Public)

> Source: `includes/class-maljani-policy-verification.php`
> The verification system is entirely WP-side. It uses WP rewrite rules and **does NOT have a GraphQL or REST endpoint today**.

### Current Mechanism

- WP Page at `/verify-policy/` handles the form submission via `GET` params.
- The shortcode `[maljani_verify_policy]` renders the lookup form.
- The form inputs: `policy_no` + `passport` (passport number).
- The result is rendered server-side in PHP.

### Headless Strategy Options (choose one):

**Option A — Embed via iframe (quick):**
```tsx
<iframe src={`${process.env.NEXT_PUBLIC_WP_SITE_URL}/verify-policy/`} style={{ width: "100%", minHeight: 400 }} />
```

**Option B — Add a REST endpoint in WP (recommended, ask WP developer):**

```
GET /wp-json/maljani/v1/verify?policy_no=MAL-1234&passport=AB123456
```

Expected response:
```json
{
  "valid": true,
  "insuredNames": "John Doe",
  "departure": "2026-04-10",
  "return": "2026-04-24",
  "region": "Schengen",
  "policyTitle": "Premium Schengen Cover",
  "status": "active"
}
```

**Option C — Use QR code URL directly:**
The WP system already generates a public verification URL of the form:
```
https://your-wp-site.com/?verify_policy=1&sale_id={id}&token={hmac}
```

Display a QR code pointing to this URL. The WP page renders the full verification page. No new frontend page needed.

---

## 11. Pages Required

Below is the complete list of pages for the Next.js app.

---

### 11.1 Public Pages

#### `/` — Homepage / Landing Page
**Purpose**: Marketing entry point with search wizard.

**Components:**
- Hero section with headline + tagline
- 3-step search wizard:
  - Step 1: Region selector (from `GetRegions` query)
  - Step 2: Departure & Return date pickers
  - Step 3: CTA "Get Quotes" → `/policies?region=<slug>&departure=<d>&return=<r>`
- "Why Maljani" features section
- Insurer partner logos
- CTA → `/auth/register`

```typescript
// Server Component / getStaticProps
const { data } = await apolloClient.query({ query: GET_REGIONS });
```

---

#### `/policies` — Policy Catalog
**URL Params:** `?region=schengen&departure=2026-04-10&return=2026-04-24`

**Components:**
- Region filter buttons (GraphQL)
- Date range picker (syncs with URL)
- Policy grid — 3–4 columns desktop (PolicyThumbnailCard, see Section 7)
- Empty state illustration

```typescript
// getServerSideProps — dynamic filters require SSR
const { data } = await apolloClient.query({
  query: GET_POLICY_THUMBNAILS,
  variables: { region: searchParams.region ?? null, first: 12 }
});
```

---

#### `/policies/[slug]` — Policy Detail Page

**Components:**
- Feature image header
- Policy name + region badges
- Tabs: Description | Benefits | Not Covered
- Pricing table (all `policyDayPremiums` brackets)
- Sticky "Buy This Policy" CTA → `/buy/[databaseId]?departure=…&return=…`
- Payment instructions (`policyPaymentDetails`)

```typescript
// getStaticProps + getStaticPaths (SSG + ISR)
const { data } = await apolloClient.query({ query: GET_POLICY, variables: { id } });
```

---

### 11.2 Auth Pages

#### `/auth/login`
- Fields: Email, Password
- Calls: `maljaniLogin` mutation
- On success: POST to `/api/auth/login` (sets httpOnly cookie) → redirect by role

#### `/auth/register`
- Toggle: Traveler / Agent
- Traveler fields: Full Name, Email, Password, Phone
- Agent fields: + Agency Name
- Calls: `maljaniRegister` mutation

#### `/auth/logout`
- API route only: clears cookie, redirects to `/`

---

### 11.3 Purchase Flow

#### `/buy/[policyId]` — 4-Step Wizard
**Guard:** `is_logged_in` — redirect to `/auth/login?next=/buy/[policyId]` if not.

| Step | Label | Key Actions |
|------|-------|-------------|
| 1 | Trip Summary | Show policy, dates, price. Dates pre-filled from URL params. |
| 2 | Insured Details | Form: name, DOB, passport/ID, phone, email, address, country |
| 3 | Payment | Initiate Pesapal or display manual payment instructions |
| 4 | Confirmation | Show `policyNumber`, `amountPaid`, PDF download link |

**Mutation called at Step 3:** `submitPolicySale`

---

#### `/buy/[policyId]/confirmation`
- Params: `?saleId=X&policyNumber=MAL-1234`
- Poll `GET /wp-json/maljani-crm/v1/policies/{saleId}` for `policy_status`
- Show success when `active`, "processing" when `pending`

---

### 11.4 Dashboard Pages

#### `/dashboard` — Router Hub
```typescript
if (!user) redirect("/auth/login");
if (role === "agent")   redirect("/dashboard/agent");
if (role === "insured") redirect("/dashboard/client");
```

#### `/dashboard/client` — Traveler Dashboard
- Guard: `insured` role
- Profile card + edit
- Table: Policy Name, Region, Departure, Return, Status, Amount, Actions (View | PDF)
- Data from: `GET /wp-json/maljani-crm/v1/policies`

#### `/dashboard/client/policy/[saleId]`
- Full sale record
- Download Certificate link (Section 9)

#### `/dashboard/agent` — Agent Dashboard
- Guard: `agent` role
- **Tab 1 — My Sales**: Table with columns: Policy Number, Insured, Policy Name, Dates, Premium, Commission, Status. Actions: Mark Received / Dispute (REST: `maljani-crm/v1/commissions`)
- **Tab 2 — New Sale**: Link to `/policies`
- **Tab 3 — Commission Statement**: totals by status
- **Tab 4 — Profile**: agency info (read-only)

---

### 11.5 Utility Pages

#### `/quote` — Standalone Quick Quote
Full-page search wizard for ad deep-links.

#### `/verify` — Public Policy Verification
- Input: Policy Number + Passport Number
- No auth required
- Backend: choose Option A/B/C from Section 10

#### `/404` and `/500`

---

## 12. Routing Map

```
/                                   Homepage (public)
/policies                           Policy Catalog (public)
/policies/[slug]                    Policy Detail (public)
/auth/login                         Login (public)
/auth/register                      Register (public)
/auth/logout                        Logout API route
/buy/[policyId]                     Purchase Wizard (protected)
/buy/[policyId]/confirmation        Post-purchase confirmation (protected)
/dashboard                          Role router (protected)
/dashboard/client                   Traveler Dashboard (insured)
/dashboard/client/policy/[saleId]   Sale detail + PDF (insured)
/dashboard/agent                    Agent Dashboard (agent)
/quote                              Quick Quote (public)
/verify                             Policy Verification (public)
```

---

## 13. State Management Notes

- **Auth state**: React Context populated from `/api/auth/me` (Next.js API route reads the httpOnly cookie and decodes the JWT). Include `id`, `name`, `email`, `role`.
- **Quote params**: URL query params only — `departure`, `return`, `region`. No global state needed; this keeps URLs shareable.
- **Purchase flow**: Local component state within the `/buy` wizard. Clear on success or navigation away.
- **Apollo cache**: `cache-first` for policies and regions (static data). `network-only` for dashboard sales data.

---

## 14. UI/Design Conventions

Match the WordPress backend's glassmorphism aesthetic for brand consistency.

| Token | Value |
|-------|-------|
| Primary | `#4f46e5` (indigo-600) |
| Primary hover | `#4338ca` (indigo-700) |
| Page background | `#f8fafc` (slate-50) |
| Card background | `rgba(255,255,255,0.7)` |
| Card blur | `backdrop-filter: blur(10px)` |
| Card border | `1px solid rgba(255,255,255,0.8)` |
| Card radius | `24px` |
| Card shadow | `0 20px 25px -5px rgba(0,0,0,0.1)` |
| Font | `Inter, Outfit, system-ui, sans-serif` |
| Heading weight | `800` |
| Success | `#22c55e` |
| Warning | `#f59e0b` |
| Error | `#ef4444` |
| Input radius | `12px` |
| Input border | `1px solid #e2e8f0` |
| Input focus ring | `box-shadow: 0 0 0 3px rgba(79,70,229,0.1)` |

**Icons:** `lucide-react` (same as WP admin side).

**Step wizards:** 3-step numbered progress bar at top.

**Responsive breakpoints:** Desktop ≥1024px (3–4 col grid) | Tablet 768–1023px (2 col) | Mobile <768px (1 col, full-width buttons).

---

## 15. What the WP Backend Still Needs

This section tells the **WP developer** exactly what PHP to add for the headless frontend to work fully.

---

### 15.1 Register Custom Post Meta as GraphQL Fields

Add this to `admin/class-maljani_policy-cpt.php` inside `register_Insurance_Policy()`, after `register_post_type()`:

```php
add_action('graphql_register_types', function() {

    // Simple string fields
    $simple_fields = [
        'policyDescription'  => '_policy_description',
        'policyCoverDetails' => '_policy_cover_details',
        'policyBenefits'     => '_policy_benefits',
        'policyNotCovered'   => '_policy_not_covered',
        'policyCurrency'     => '_policy_currency',
        'policyPaymentDetails' => '_policy_payment_details',
    ];

    foreach ($simple_fields as $gql_key => $meta_key) {
        register_graphql_field('Policy', $gql_key, [
            'type'        => 'String',
            'description' => 'Policy meta: ' . $meta_key,
            'resolve'     => function($post) use ($meta_key) {
                return get_post_meta($post->databaseId, $meta_key, true) ?: '';
            }
        ]);
    }

    // Premium brackets (array of {from, to, premium})
    register_graphql_object_type('PolicyPremiumBracket', [
        'description' => 'A day-range premium bracket.',
        'fields'      => [
            'from'    => ['type' => 'Int'],
            'to'      => ['type' => 'Int'],
            'premium' => ['type' => 'Float'],
        ]
    ]);

    register_graphql_field('Policy', 'policyDayPremiums', [
        'type'        => ['list_of' => 'PolicyPremiumBracket'],
        'description' => 'Premium schedule by trip duration.',
        'resolve'     => function($post) {
            $brackets = get_post_meta($post->databaseId, '_policy_day_premiums', true);
            if (!is_array($brackets)) return [];
            return array_map(fn($b) => [
                'from'    => intval($b['from'] ?? 0),
                'to'      => intval($b['to'] ?? 0),
                'premium' => floatval($b['premium'] ?? 0),
            ], $brackets);
        }
    ]);
});
```

---

### 15.2 Add Public Verify Policy REST Endpoint

Add to `includes/api/class-maljani-api-endpoints.php` inside `register_routes()`:

```php
register_rest_route('maljani/v1', '/verify', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'args'                => [
        'policy_no' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
        'passport'  => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
    'callback'            => function($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $sale  = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE policy_number = %s AND passport_number = %s LIMIT 1",
            $request->get_param('policy_no'),
            $request->get_param('passport')
        ));
        if (!$sale) {
            return new WP_REST_Response(['valid' => false, 'message' => 'No matching policy found.'], 404);
        }
        return new WP_REST_Response([
            'valid'        => true,
            'insuredNames' => $sale->insured_names,
            'departure'    => $sale->departure,
            'return'       => $sale->return,
            'region'       => $sale->region,
            'policyTitle'  => get_the_title(intval($sale->policy_id)),
            'status'       => $sale->policy_status,
        ]);
    }
]);
```

---

### 15.3 Secure PDF Download with Signed Token

Add a new REST endpoint so the headless Next.js frontend can link directly to PDFs without requiring a WP session cookie:

```php
register_rest_route('maljani/v1', '/policy-pdf', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'args'                => [
        'sale_id' => ['required' => true, 'sanitize_callback' => 'intval'],
        'token'   => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
    'callback'            => function($request) {
        $sale_id = $request->get_param('sale_id');
        $token   = $request->get_param('token');
        global $wpdb;
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}policy_sale WHERE id = %d LIMIT 1", $sale_id
        ));
        if (!$sale) return new WP_REST_Response(['error' => 'Not found'], 404);

        $expected = Maljani_PDF_Generator::generate_verification_hash(
            $sale_id, $sale->policy_number, $sale->passport_number
        );
        if (!hash_equals($expected, $token)) {
            return new WP_REST_Response(['error' => 'Invalid token'], 403);
        }

        // Delegate to the existing PDF generation
        require_once plugin_dir_path(__FILE__) . '../generate-policy-pdf.php';
        exit; // PDF generator sends headers and output
    }
]);
```

**Frontend usage:**
```typescript
// Build token client-side is NOT possible (secret is WP-side).
// Instead, have the WP backend return a pre-signed PDF URL as part of the sale response:
// e.g., extend submitPolicySale outputFields to include pdfUrl.
// OR call GET /wp-json/maljani-crm/v1/policies/{saleId} and have it return a signed pdfUrl.
```

> Simplest implementation: Add `pdfUrl` to `GET /wp-json/maljani-crm/v1/policies/{id}` response, generated server-side with `Maljani_PDF_Generator::generate_verification_hash()`.

---

### 15.4 Summary of Backend Work Required

| # | Task | Priority | Section |
|---|------|----------|---------|
| 1 | Register 7 custom post meta fields as GraphQL fields on `Policy` type | **CRITICAL** | 15.1 |
| 2 | Add `GET /wp-json/maljani/v1/verify` REST endpoint | High | 15.2 |
| 3 | Add signed `pdfUrl` to sale REST response | High | 15.3 |
| 4 | Confirm `GET /wp-json/maljani-crm/v1/policies` filters by the current user's role | High | 6.1 |
| 5 | Confirm Pesapal mode (`maljani_pesapal_mode`) is set to `live` on production | Medium | 8 |

---

*Document version: 2.0.0 | Date: 2026-03-19 | Verified against plugin source code | Prepared for: Next.js frontend team / AI agent*
