# React Development Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Is `eslint-plugin-react-hooks` enabled with `rules-of-hooks` and `exhaustive-deps` as errors?
- Is every `useEffect` synchronizing with something outside React, not deriving state or handling events?
- Are fetches cancelled or ignored on unmount and re-run, or handled by TanStack Query / router loaders?
- Do dynamic lists use stable keys (not array index)?
- Are objects, arrays, and callbacks created in render either moved out, memoized where measured, or handled by the React Compiler?
- Are context values split by update frequency and memoized?
- Is server state kept in a query cache rather than copied into `useState`?
- Is shareable UI state (filters, tabs, pagination) in the URL?
- Do inputs stay controlled or uncontrolled for their whole life?
- Are error boundaries paired with Suspense boundaries around data-driven regions?
- Next.js App Router: is the `"use client"` boundary as low as possible, and are server actions validating input?
- React 19: are `ref` as prop, Actions, and `use()` used only where the libraries support them?
- Do interactive elements use native semantics, labels, visible focus, keyboard paths, and `prefers-reduced-motion`?
- Are routes and heavy features code-split, and long lists virtualized?
- Do tests query by role and label, use `userEvent` and MSW, and cover the critical flow in Playwright?
- Did `tsc --noEmit`, ESLint with zero warnings, unit tests, and a production build pass?
