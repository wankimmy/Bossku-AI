# Go Development Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Does every function that does I/O, waits, or can be cancelled take `ctx context.Context` first and honor it?
- Does every goroutine have an owner, a stop signal, and an error path (`errgroup`, `WaitGroup`, channel close)?
- Are errors wrapped with `%w`, checked with `errors.Is`/`errors.As`, and handled or logged exactly once?
- Do HTTP clients have timeouts, and are response bodies closed and drained?
- Does the server set read/write/idle timeouts and shut down gracefully on SIGTERM?
- Are `rows.Close()`, `rows.Err()`, and `defer tx.Rollback()` present on every query and transaction?
- Is the DB pool size bounded against the database's connection limit?
- Are shared maps and slices protected, and was `go test -race` run?
- Are sqlc/pgx type mappings (`interval`, `numeric`, nullable columns) converted in one place?
- Is `time.Now()` injected as a clock where tests depend on time?
- Is `init()` free of I/O and env reads?
- Are secrets read from env or a secrets manager, never from flags or build args?
- Do tests cover the handler with `httptest` and the repository against a real database?
- Was the binary built with `CGO_ENABLED=0 -trimpath -ldflags="-s -w"` on a distroless or scratch base as non-root?
- Did `go vet`, `golangci-lint` (or `staticcheck`), and `govulncheck` pass?
