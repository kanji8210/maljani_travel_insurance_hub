# Architectural Decision Records (ADR)

Log key architectural decisions, their context, and trade-offs here.

## Template
### ADR-XXX: Decision Title (YYYY-MM-DD)

**Context:**
- Why the decision was needed
- What problem it solves

**Decision:**
- What was chosen

**Alternatives Considered:**
- Option 1 -> Why rejected

**Consequences:**
- Benefits
- Trade-offs

---

### ADR-001: Implementing Agentic AI Skills (2026-03-16)

**Context:**
- Need to extend the AI assistant's capabilities with specialized design and memory functions.

**Decision:**
- Adopted the `SKILL.md` standard and installed `frontend-design` and `project-memory` skills into the `.agents/skills/` directory.

**Alternatives Considered:**
- Putting instructions in system prompts -> Rejected (too much context bloat).

**Consequences:**
- Modular, portable skills that provide high-quality design and persistent memory.

### ADR-002: Insurer-Issued Policy Numbers (2026-08-04)

**Context:**
- TIC-Kenya staff issue policies manually by entering customer data on each insurer's website.
- A policy number does not exist until the insurer completes issuance.

**Decision:**
- Use the sale ID as the request reference before issuance.
- Store `policy_number` only when an admin records the number returned by the insurer.
- Require that number before a request can become Issued or Active.

**Consequences:**
- Customer and admin interfaces distinguish requests from issued policies.
- The workflow cannot expose a generated internal reference as an insurer policy number.
