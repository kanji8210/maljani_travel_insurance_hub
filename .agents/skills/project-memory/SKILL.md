---
name: project-memory
description: Set up and maintain a structured project memory system in docs/project_notes/ that tracks bugs with solutions, architectural decisions, key project facts, and work history. Use this skill when asked to "set up project memory", "track our decisions", "log a bug fix", "update project memory", or "initialize memory system". Configures both CLAUDE.md and AGENTS.md to maintain memory awareness across different AI coding tools.
---

# Project Memory

## Table of Contents

- [Overview](#overview)
- [When to Use This Skill](#when-to-use-this-skill)
- [Core Capabilities](#core-capabilities)
  - [1. Initial Setup - Create Memory Infrastructure](#1-initial-setup---create-memory-infrastructure)
  - [2. Configure CLAUDE.md - Memory-Aware Behavior](#2-configure-claudemd---memory-aware-behavior)
  - [3. Configure AGENTS.md - Multi-Tool Support](#3-configure-agentsmd---multi-tool-support)
  - [4. Searching Memory Files](#4-searching-memory-files)
  - [5. Updating Memory Files](#5-updating-memory-files)
  - [6. Memory File Maintenance](#6-memory-file-maintenance)
- [Templates and References](#templates-and-references)
- [Example Workflows](#example-workflows)
- [Integration with Other Skills](#integration-with-Other-skills)
- [Success Criteria](#success-criteria)

## Overview

Maintain institutional knowledge for projects by establishing a structured memory system in `docs/project_notes/`. This skill sets up four key memory files (bugs, decisions, key facts, issues) and configures CLAUDE.md and AGENTS.md to automatically reference and maintain them. The result is a project that remembers past decisions, solutions to problems, and important configuration details across coding sessions and across different AI tools.

## When to Use This Skill

Invoke this skill when:

- Starting a new project that will accumulate knowledge over time
- The project already has recurring bugs or decisions that should be documented
- The user asks to "set up project memory" or "track our decisions"
- The user wants to log a bug fix, architectural decision, or completed work
- Encountering a problem that feels familiar ("didn't we solve this before?")
- Before proposing an architectural change (check existing decisions first)
- Working on projects with multiple developers or AI tools (Claude Code, Cursor, etc.)

## Core Capabilities

### 1. Initial Setup - Create Memory Infrastructure

When invoked for the first time in a project, create the following structure:

```
docs/
└── project_notes/
    ├── bugs.md         # Bug log with solutions
    ├── decisions.md    # Architectural Decision Records
    ├── key_facts.md    # Project configuration and constants
    └── issues.md       # Work log with ticket references
```

**Directory naming rationale:** Using `docs/project_notes/` instead of `memory/` makes it look like standard engineering organization, not AI-specific tooling. This increases adoption and maintenance by human developers.

### 2. Configure CLAUDE.md - Memory-Aware Behavior

Add or update the following section in the project's `CLAUDE.md` file:

```markdown
## Project Memory System

This project maintains institutional knowledge in `docs/project_notes/` for consistency across sessions.

### Memory Files

- **bugs.md** - Bug log with dates, solutions, and prevention notes
- **decisions.md** - Architectural Decision Records (ADRs) with context and trade-offs
- **key_facts.md** - Project configuration, credentials, ports, important URLs
- **issues.md** - Work log with ticket IDs, descriptions, and URLs

### Memory-Aware Protocols

**Before proposing architectural changes:**
- Check `docs/project_notes/decisions.md` for existing decisions
- Verify the proposed approach doesn't conflict with past choices
- If it does conflict, acknowledge the existing decision and explain why a change is warranted

**When encountering errors or bugs:**
- Search `docs/project_notes/bugs.md` for similar issues
- Apply known solutions if found
- Document new bugs and solutions when resolved

**When looking up project configuration:**
- Check `docs/project_notes/key_facts.md` for credentials, ports, URLs, service accounts
- Prefer documented facts over assumptions

**When completing work on tickets:**
- Log completed work in `docs/project_notes/issues.md`
- Include ticket ID, date, brief description, and URL

**When user requests memory updates:**
- Update the appropriate memory file (bugs, decisions, key_facts, or issues)
- Follow the established format and style (bullet lists, dates, concise entries)

### Style Guidelines for Memory Files

- **Prefer bullet lists over tables** for simplicity and ease of editing
- **Keep entries concise** (1-3 lines for descriptions)
- **Always include dates** for temporal context
- **Include URLs** for tickets, documentation, monitoring dashboards
- **Manual cleanup** of old entries is expected (not automated)
```

### 3. Configure AGENTS.md - Multi-Tool Support

If the project has an `AGENTS.md` file (used for agent workflows or multi-tool projects), add the same memory protocols. This ensures consistency whether using Claude Code, Cursor, GitHub Copilot, or other AI tools.

### 4. Searching Memory Files

When encountering problems or making decisions, proactively search memory files using grep or find tools.

### 5. Updating Memory Files

When the user requests updates or when documenting resolved issues, update the appropriate memory file following the established format (bullet lists, dates, concise entries).

### 6. Memory File Maintenance

Periodically clean old entries and update key_facts.md when project configuration changes.

## Success Criteria

This skill is successfully deployed when:
- `docs/project_notes/` directory exists with all four memory files
- CLAUDE.md includes "Project Memory System" section with protocols
- Memory files follow style guidelines
