# Memory viewer UI spec

Audience: humans auditing what BosskuAI “remembers.”  
Data model: [`../memory/schema.md`](../memory/schema.md) · human card layout [`../memory/readable-memory-format.md`](../memory/readable-memory-format.md)

## Design principles

- Mobile responsive, clean dashboard, generous whitespace
- **No** generic “AI SaaS” chrome: avoid loud gradients, gratuitous glassmorphism
- Clear typography hierarchy, easy scan patterns
- Dark + light themes (toggle in app chrome)
- Feels like **internal tooling**, not a landing page

## Information architecture

```text
Memory Dashboard
├── Search memory
├── Filter by project
├── Filter by memory type
├── Filter by importance
├── View raw JSON
├── View human-readable summary
├── Edit memory
├── Delete memory
└── Export memory
```

## Memory types (filter chips)

- User Preference (`user_preference`)
- Project Rule (`project_rule`)
- Architecture Decision (`architecture_decision`)
- Code Standard (`code_standard`)
- Bug History (`bug_history`)
- Deployment Note (`deployment_note`)
- Session Summary (`session_summary`)
- Skill Knowledge (`skill_knowledge`)

## Map to current Nuxt MVP

| Requirement | Current surface | Notes |
|---|---|---|
| Search + list | [`web/pages/memory/index.vue`](../web/pages/memory/index.vue) | Extend with filters + export |
| Raw vs human | new tab or split drawer | Render card per `readable-memory-format.md` |
| CRUD | API + UI buttons | Wire delete/edit with confirm + audit log |
| Export | download JSONL + `cards.md` | Server endpoint or client-side bundle |

## Acceptance criteria (MVP increment)

1. User can find a memory by substring **< 300 ms** client-side on cached page.
2. Filters stack (AND) across type + importance + project.
3. Delete requires **type-to-confirm** on `importance=high`.
4. Export produces **redacted** JSONL (strip secret patterns server-side).
