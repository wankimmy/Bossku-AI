---
name: bosskuai-react-development
description: "Use this for React 18/19 and TypeScript frontend work — component and hook design, choosing state management (local, context, Zustand, TanStack Query, Redux Toolkit), data fetching and caching, forms, routing with React Router, TanStack Router or Next.js App Router, rendering performance, accessibility, and testing with Testing Library and Playwright. Also use to audit an existing React codebase. Vue/Nuxt belongs to bosskuai-nuxt-development; React Native to bosskuai-expo-react-native."
---

# BosskuAI React Development

Use this skill when the frontend is React and the answer depends on React's rendering model, hook rules, or ecosystem choices rather than on generic UI advice.

## How this differs from nearby skills

- **`bosskuai-nuxt-development`**: Vue/Nuxt equivalent; do not mix conventions.
- **`bosskuai-expo-react-native`**: React on mobile; different primitives, navigation, and performance model.
- **`bosskuai-ui-ux-design-to-code`** / **`bosskuai-taste`**: what the interface should look like; this skill builds it correctly in React.
- **`bosskuai-web-performance`**: Core Web Vitals and bundle strategy; this skill fixes React-level render cost.
- **`animate`** (emil-skills): motion craft; this skill wires it, it does not design it.

## Mindset

- Server state and client state are different problems; treat fetched data as a cache, not as component state.
- Derive, don't synchronize: if a value can be computed from props or other state, compute it during render.
- Components render; hooks orchestrate; modules hold logic that has nothing to do with React.
- TypeScript strict is the baseline; `any` in a component boundary is a bug report waiting to happen.
- Accessibility is correctness, not polish.

## Orient before changing anything

1. `package.json`: React major (19 changes `ref`, `forwardRef`, Actions, `use()`), framework (Vite SPA, Next.js App or Pages Router, Remix/React Router 7 framework mode), TypeScript `strict`.
2. State and data layers already present: TanStack Query, SWR, Redux Toolkit, Zustand, Jotai, Apollo. Use what exists.
3. Lint config: `eslint-plugin-react-hooks` (`rules-of-hooks`, `exhaustive-deps`) must be on. React Compiler enabled? Then manual `useMemo`/`useCallback` is mostly noise.
4. Test stack: Vitest/Jest + Testing Library, MSW, Playwright/Cypress.
5. Design system or component library (shadcn/ui, Radix, MUI, Chakra) and its theming approach.

## Rules that catch most React bugs

- `useEffect` is for synchronizing with something outside React (subscriptions, DOM APIs, timers). Not for derived state, not for reacting to a click, not for chaining state updates.
- Fetching in `useEffect` without an abort or ignore flag races on fast navigation; use TanStack Query, a router loader, or `AbortController`.
- Missing or index-based `key` on dynamic lists corrupts state on reorder.
- Objects, arrays, and functions created during render are new every time: they retrigger effects, break `memo`, and cause context consumers to re-render. Move them out, memoize, or split the context.
- Context value objects re-render every consumer; split by update frequency (auth vs theme vs high-churn UI state).
- Storing derived or prop-mirrored data in `useState` creates two sources of truth.
- Conditional or early-returned hooks violate hook order.
- Switching an input between controlled and uncontrolled (undefined → value) throws away user input.
- No error boundary around data-driven regions means one bad response blanks the page; pair Suspense boundaries with error boundaries.
- Next.js App Router: server components cannot use hooks or browser APIs; `"use client"` at the wrong level drags the whole subtree to the client; server actions must validate input like any API; `fetch` caching semantics changed in 15 (uncached by default).
- React 19: `ref` is a normal prop; `useActionState`/`useOptimistic` replace hand-rolled pending states; `use()` reads promises and context conditionally; check library compatibility before upgrading.

## State decision table

| State kind | Put it in |
|---|---|
| Shareable UI state (filters, tabs, pagination) | the URL (search params) |
| Component-local | `useState` / `useReducer` |
| Low-frequency global (auth user, theme, locale) | context, memoized value |
| High-frequency client state shared widely | Zustand or Jotai |
| Server data (lists, details, mutations) | TanStack Query with keys, invalidation, optimistic updates |
| Complex event-sourced or already present | Redux Toolkit |
| Forms | react-hook-form + zod schema; server errors mapped to fields |

## Performance in React terms

- Measure first with React DevTools Profiler; fix the component that renders too often or too expensively, not everything.
- Virtualize long lists (TanStack Virtual); paginate or window server data.
- Code-split by route and by heavy feature (`lazy` + Suspense); keep the initial bundle for the first screen only.
- `memo`/`useMemo`/`useCallback` only after measuring, or not at all with the React Compiler.
- Avoid layout thrash: batch DOM reads/writes; use `useLayoutEffect` only for measurement that must precede paint.
- Hand bundle size, images, fonts, and Core Web Vitals to `bosskuai-web-performance`.

## Accessibility baseline

- Native elements first (`button`, `a`, `label`, `dialog`); ARIA only when HTML lacks the semantics.
- Focus management on route change and modal open/close; visible focus ring; Escape closes overlays.
- Every input has a label; errors are announced (`aria-describedby`, `aria-live`).
- Color contrast ≥ 4.5:1 for text; honor `prefers-reduced-motion`.

## Testing

- Query like a user: `getByRole`, `getByLabelText`; avoid test ids unless nothing else is stable.
- `userEvent` over `fireEvent`; MSW for network; no mocking of hooks or internal state.
- One Playwright flow per critical path (auth, checkout, core CRUD); screenshot only where layout is the requirement.
- Type-level tests for public component props when they are a contract.

## Verification

```bash
tsc --noEmit
eslint . --max-warnings=0
vitest run            # or jest
playwright test       # critical flows
vite build && npx vite-bundle-visualizer   # or next build && ANALYZE=true
```

## Guardrails

- Do not add a state library to solve a problem TanStack Query or the URL already solves.
- Do not silence `exhaustive-deps`; fix the dependency or restructure.
- Do not fetch in `useEffect` when the framework provides loaders or server components.
- Do not ship `any` at component or API boundaries.
- Do not treat a green unit suite as proof the flow works; run the Playwright path.

## Output format

```text
React: [18 | 19] - Framework: [Vite | Next App Router | Next Pages | React Router 7] - TS strict: [yes/no]
State/data layers honored: [...]

Findings:
  P0/P1/P2 - [file:line] - [issue] - [fix]

Change plan: [smallest correct change]
Verification: [commands run and result]
```

## References

- `../../references/checklists/react-development-checklist.md`
- `../../references/checklists/ui-fidelity-checklist.md`
- `../../references/checklists/anti-ai-ui-checklist.md`
