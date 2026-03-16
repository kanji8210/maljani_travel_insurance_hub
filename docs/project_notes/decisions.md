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
