# BosskuAI Repo Maintainability Audit

**Date:** 2026-04-13
**Auditor:** Senior staff engineer pass
**Scope:** Full repo — integrity, drift, validation, release safety, documentation

---

## Executive summary

The repo is structurally sound and existing validation scripts pass. Core issues are:

1. **Hardcoded skill count** in README drifted from reality (says 63; actual is 65 folders / 52 active indexed).
2. **10 skill folders orphaned** from `skill-index.json` (present in `AGENTS.md` local skills table but missing from machine-readable index).
3. **No CI** — zero automated enforcement on push/PR.
4. **No skill-index validation script** — orphan detection was manual until this audit.
5. `.DS_Store` in `ai-assistant/skills/` (covered by .gitignore but present in working tree).

No missing required files. `check-workspace.sh` and `verify-skill-references.sh` both pass.

---

## Findings

### F1 — Hardcoded skill count in README (HIGH drift risk)

**File:** `README.md` lines 26 and 49
**Problem:** README claims "63 skills" in two places. Actual state:
- Skill folders: 65
- skill-index.json active: 52, deprecated aliases: 3 → 55 total indexed
- AGENTS.md local skills table: ~62 rows

The count is wrong and will continue drifting with every new skill added.

**Fix:** Remove hardcoded count; replace with prose that doesn't require updating.

---

### F2 — 10 skill folders missing from skill-index.json (MEDIUM)

**File:** `skill-index.json`
**Problem:** These folders exist under `ai-assistant/skills/` and are listed in `AGENTS.md` but have no entry in `skill-index.json`:

| Folder | Missing from index |
|--------|-------------------|
| `bosskuai-claude-code-setup` | ✗ |
| `bosskuai-claude-md-management` | ✗ |
| `bosskuai-cross-model-escalation` | ✗ |
| `bosskuai-github-workflow` | ✗ |
| `bosskuai-incident-response` | ✗ |
| `bosskuai-integration-testing` | ✗ |
| `bosskuai-mongodb` | ✗ |
| `bosskuai-operations` | ✗ |
| `bosskuai-performance-profiling` | ✗ |
| `bosskuai-skill-creator` | ✗ |

`AGENTS.md` and the skill folders are the canonical source; `skill-index.json` is lagging.

**Fix:** Add all 10 to `skill-index.json` with correct cluster/triggers/keywords.

---

### F3 — No CI (HIGH — release safety gap)

**File:** `.github/` (missing)
**Problem:** No automated checks run on push or PR. Any contributor can introduce broken paths, orphan skills, or workspace check failures without detection.

**Fix:** Add `.github/workflows/integrity-check.yml` running:
- `scripts/check-workspace.sh`
- `scripts/verify-skill-references.sh`
- `scripts/validate-skill-index.sh` (new)

---

### F4 — No skill-index validation script (MEDIUM)

**File:** `scripts/` (gap)
**Problem:** There is no executable check that detects skill-index ↔ folder divergence. This allowed F2 to go unnoticed. The only existing script (`verify-skill-references.sh`) checks SKILL.md reference paths, not index completeness.

**Fix:** Add `scripts/validate-skill-index.sh` that checks:
- Every indexed active skill has a folder with a SKILL.md
- No orphan folders exist without an index entry (excluding `.DS_Store`)
- `check-workspace.sh` required memory files exist
- README does not contain a stale skill count pattern (informational only)

---

### F5 — `.DS_Store` in skills directory (LOW)

**File:** `ai-assistant/skills/.DS_Store`
**Problem:** macOS artifact present in working tree. `.gitignore` covers it at root but this was still committed or created locally.
**Fix:** Already in `.gitignore`; advise `git rm --cached` if tracked. No code change needed.

---

### F6 — No audits directory (LOW — resolved by this file)

**File:** `ai-assistant/references/audits/` (created by this audit)
**Problem:** No structured location for audit artifacts.
**Fix:** This directory now exists.

---

## What is working well

- `check-workspace.sh` — comprehensive, well-written, correct required-file list
- `verify-skill-references.sh` — correctly validates `../../references/` paths in SKILL.md files; security check for suspicious paths is good
- `install.sh` / `install.ps1` — feature parity is solid; `--sync-layer`, `--preserve-memory`, `--skills-only` all implemented on both
- Memory template files are present and not empty
- `.gitignore` is appropriate
- AGENTS.md structure and DoD checklist are high quality
- ADRs provide good decision audit trail

---

## Validated counts (post-fix)

| Source | Active skills | Deprecated aliases | Total |
|--------|-------------|-------------------|-------|
| `ai-assistant/skills/` folders | 65 | 0 | 65 |
| `skill-index.json` (post-fix) | 62 | 3 | 65 |
| `AGENTS.md` local skills table | ~62 | 3 | ~65 |

---

## Changes made in this audit pass

1. Created `ai-assistant/references/audits/` directory
2. Written this audit report
3. Added 10 orphan skills to `skill-index.json`
4. Removed hardcoded "63" count from `README.md`
5. Added `scripts/validate-skill-index.sh`
6. Added `.github/workflows/integrity-check.yml`
7. Removed redundant "New and extended skill routing" tables from `.codex/AGENTS.md` and `.cursor/rules/bosskuai.mdc` — all 14 skills covered in root `AGENTS.md` § Skill roster; tool-specific files now point there

---

## Local validation commands

```bash
# Core workspace check
./scripts/check-workspace.sh .

# Skill reference integrity
./scripts/verify-skill-references.sh

# Skill index consistency (new)
./scripts/validate-skill-index.sh

# Run all three
./scripts/check-workspace.sh . && ./scripts/verify-skill-references.sh && ./scripts/validate-skill-index.sh
```

---

## Unresolved / out of scope

- `.DS_Store` in working tree: run `git rm --cached ai-assistant/skills/.DS_Store` if it is tracked
- `CLAUDE.original.md` at repo root: appears to be a historical backup; consider archiving to `ai-assistant/references/` or deleting if no longer needed
- `install.ps1` runs `check-workspace.sh` via bash (Windows-only path); this is intentional and correct — documented in usage
- skill-index.json `generated_from: AGENTS.md` claim: the index is maintained manually, not generated. This is a minor accuracy issue; no fix applied since auto-generation would be over-engineering for the current scale
