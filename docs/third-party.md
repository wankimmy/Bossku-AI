# Third-party skill packs

BosskuAI vendors Agent Skills from these MIT-licensed upstream projects. Provenance is tracked in [`skills/vendored.json`](../skills/vendored.json).

| Pack | Upstream | License | Copyright |
|---|---|---|---|
| marketingskills | [coreyhaines31/marketingskills](https://github.com/coreyhaines31/marketingskills) | MIT | Copyright (c) 2025 Corey Haines |
| superpowers | [obra/superpowers](https://github.com/obra/superpowers) | MIT | Copyright (c) 2025 Jesse Vincent |
| hallmark | [togethercomputer/hallmark](https://github.com/togethercomputer/hallmark) | MIT | Copyright (c) 2026 Hallmark contributors |
| browser-use | [browser-use/browser-use](https://github.com/browser-use/browser-use) | MIT | Copyright (c) 2024 Gregor Zunic |
| graphify | [Graphify-Labs/graphify](https://github.com/Graphify-Labs/graphify) | MIT | Copyright (c) 2026 Safi Shamsi |
| markitdown | [microsoft/markitdown](https://github.com/microsoft/markitdown) | MIT | Copyright (c) Microsoft Corporation |
| loop-engineering | [cobusgreyling/loop-engineering](https://github.com/cobusgreyling/loop-engineering) | MIT | Copyright (c) 2026 Cobus Greyling and contributors |
| taste-skill | [Leonxlnx/taste-skill](https://github.com/Leonxlnx/taste-skill) | MIT | Copyright (c) 2026 Leonxlnx |
| scroll-world | [oso95/scroll-world](https://github.com/oso95/scroll-world) | MIT | Copyright (c) 2026 cyw |
| dcg | [Dicklesworthstone/destructive_command_guard](https://github.com/Dicklesworthstone/destructive_command_guard) | MIT with OpenAI/Anthropic rider | Copyright (c) 2026 Jeffrey Emanuel |
| emil-skills | [emilkowalski/skills](https://github.com/emilkowalski/skills) | MIT | Copyright (c) 2026 Emil Kowalski |
| graft | [NanoNets/Graft](https://github.com/NanoNets/Graft) | MIT | Copyright (c) 2026 Context Graph Engine contributors |

`markitdown` is a thin Bossku-authored skill that documents the upstream CLI; the Microsoft package is not bundled.

`graft` vendors the upstream `SKILL.md` body verbatim; the CLI itself is not bundled (`npm install -g @nanonets/graft`, then `graft build` per repo). One local deviation: the frontmatter `description` was rewritten to state the `graft/`-index precondition, because upstream ships inside an already-indexed repo and asserts it flatly. Re-apply that edit on the next re-vendor.

`dcg` is a thin Bossku skill that documents the upstream Destructive Command Guard CLI/hooks; the Rust binary is not bundled. Upstream license is MIT **with an OpenAI/Anthropic rider** — read the upstream `LICENSE` before redistributing the binary or derivative works.

## Review cadence

Models improve faster than vendored prompt text does, so packs are reviewed on a
rolling window rather than only when something breaks. `skills/vendored.json` records
`provenance.<pack>.last_synced` and a shared `review_days` (default 180).

```bash
python -m bossku skills stocktake            # age every pack against the window
python -m bossku skills stocktake --strict   # exit 1 when a pack is overdue (CI)
```

`bossku validate` prints a warning for overdue packs but stays green - a pack going
stale is a prompt to review, not a broken repo. Missing provenance *is* a hard error.

To refresh a pack: re-copy from upstream, re-run `bossku skills index`, then set
`last_synced` to today in `skills/vendored.json`.

These dates are recorded syncs, not a live upstream check - the tool reports that a
pack is due for review, never that upstream actually changed.
