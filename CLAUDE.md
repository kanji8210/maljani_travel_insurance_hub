# Maljani Travel Insurance Hub Guidelines

This project maintains persistent institutional knowledge in `docs/project_notes/` for consistency across sessions.

## Build and Test Commands
- **Check PHP Lint**: `find . -name "*.php" -exec php -l {} \;`
- **Run Tests**: `npm test` (if applicable) or PHPUnit commands.

## Code Style
- **WordPress Standards**: Follow WordPress PHP coding standards.
- **Modern CSS**: Use glassmorphism, smooth transitions, and Inter/Outfit fonts for premium UI.
- **PHP**: Use semantic naming and proper class structures (PSR-4 where possible).

## Project Memory System

### Memory Files
- **bugs.md**: Bug log with dates, solutions, and prevention notes.
- **decisions.md**: Architectural Decision Records (ADRs).
- **key_facts.md**: Project configuration, constants, and URLs.
- **issues.md**: Work log and session history.

### Memory-Aware Protocols
- **Before proposals**: Check `docs/project_notes/decisions.md` for existing architectural choices.
- **When debugging**: Search `docs/project_notes/bugs.md` for similar issues and solutions.
- **After work**: Update `docs/project_notes/issues.md` with completed tasks.
- **When configuration changes**: Update `docs/project_notes/key_facts.md`.

---
*Created by Antigravity on 2026-03-16*
