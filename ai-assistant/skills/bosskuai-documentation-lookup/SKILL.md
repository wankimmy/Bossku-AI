# Documentation Lookup

## When to use
- Code depends on specific framework or library behavior and you need to verify it
- User asks "how do I configure X in [library]?" or "what's the API for Y?"
- Working with a library version that may differ from training data knowledge
- Answering questions about obscure or recently changed API signatures
- Any time version-specific behavior matters (breaking changes, new features, deprecated methods)
- Avoiding hallucinated API signatures by grounding answers in live docs

## How this differs from nearby skills
- **vs search-first:** search-first checks the existing codebase for prior solutions and patterns already in use. documentation-lookup fetches external, authoritative library documentation via Context7.
- **vs polyglot-engineering:** polyglot-engineering handles cross-language translation and idiomatic patterns. documentation-lookup is specifically for fetching accurate, current external API docs.
- **vs coding-best-practices:** coding-best-practices applies general principles. documentation-lookup answers factual questions about specific APIs.
- **Training data vs Context7:** Always prefer Context7 for version-specific behavior, configuration options, and exact API signatures. Use training data only as fallback when Context7 is unavailable.

## MCP requirements
- **Context7 (required).** This skill cannot function without it.
- Graceful degradation: if Context7 is unavailable, answer from training data and explicitly state: "This is from training data and may not reflect your installed version. Verify against official docs."

## Workflow

### 1. Resolve the library ID
Use Context7 `resolve-library-id` with the library name.
- Input: library name as the user stated it (e.g., "next.js", "prisma", "fastapi")
- Review results: match by name, ecosystem, and version if provided
- If multiple matches: select the highest-confidence match with the correct package registry

### 2. Select the best match
From the resolution results, confirm:
- Correct package name and ecosystem (npm, PyPI, etc.)
- Version matches the project's installed version if known
- Score/confidence is acceptable

### 3. Fetch docs via query
Use Context7 `get-library-docs` with the resolved library ID and a focused query string.
- Query should be specific: "middleware configuration", "streaming response API", "authentication setup"
- If the first query is too broad, narrow and re-query

### 4. Apply to the current task
- Extract the relevant sections from the documentation
- Apply directly to the code or question at hand
- Cite the library name and version in the response so the user can verify
- If the docs reveal the user's approach is wrong or outdated, flag this before proceeding

## Output format
- Relevant documentation excerpt (quoted or paraphrased with attribution)
- Applied answer: how the docs answer the specific question or task
- Version note: which version the docs were fetched for
- Any caveats (breaking changes, deprecations, version mismatches)

## Guardrails
- Always verify the library ID before querying — do not guess IDs.
- Prefer version-specific docs when the project has a pinned version.
- If Context7 is unavailable, explicitly label the answer as training data with a verification reminder.
- Do not silently use training data when Context7 is available — always attempt the lookup first.
- If docs conflict with user's existing code, surface the conflict rather than silently overriding.
