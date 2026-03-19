# 🔌 GraphQL API Reference - Headless Maljani

## 1. Introduction
The Maljani Hub supports headless operation via WPGraphQL. This allows external mobile apps and web platforms to integrate the full insurance purchase flow.

## 2. Security Layers
The API is protected by multiple security layers configurable in **Maljani Settings > GraphQL & App Security**.

### 🛡️ Level 1: CORS Protection
Restricts which web domains can access the API. 
**Access Control**: Enter your front-end domains (e.g., `https://my-app.com`) in the "Allowed Front-end Origins" setting.

### 🛡️ Level 2: Application Secret
All requests must include the `X-Maljani-App-Secret` header. This identifies the request as coming from your official application.
**Header**: `X-Maljani-App-Secret: <your_secret_key>`

### 🛡️ Level 3: User Authentication (JWT)
User-level actions (like `submitPolicySale`) require a valid JWT. Use the `loginUser` mutation to obtain a token.
**Header**: `Authorization: Bearer <your_jwt_token>`

### 🛡️ Level 4: Brute Force Protection
The system tracks failed login attempts. If an IP exceeds the configured "Max Login Retries", it will be blocked for 1 hour.

## 3. Core Mutations
### Login Mutation
```graphql
mutation Login {
  loginUser(input: {
    username: "your_email@example.com",
    password: "your_password"
  }) {
    authToken
    user {
      name
      roles
    }
  }
}
```

## 3. Core Mutations
### User Registration (`registerUser`)
Create an agent or insured account.
**Inputs:**
- `fullName`: String
- `email`: String
- `password`: String
- `accountType`: "agent" | "insured"
- `phone`: String
- `agencyName`: String (Optional for agents)

### Policy Purchase (`submitPolicySale`)
Submit a purchase request.
**Inputs:**
- `policyId`: Int
- `departure`: "YYYY-MM-DD"
- `return`: "YYYY-MM-DD"
- `insuredNames`: String
- `insuredEmail`: String
- ... (standard client data)

## 4. Querying Data
### Get Policies
```graphql
query GetPolicies {
  policies {
    nodes {
      id
      title
      excerpt
      policyDayPremiums {
        from
        to
        premium
      }
    }
  }
}
```

### Get Regions
```graphql
query GetRegions {
  policyRegions {
    nodes {
      name
      slug
    }
  }
}
```

---
*Status: Active | Endpoint: /graphql*
