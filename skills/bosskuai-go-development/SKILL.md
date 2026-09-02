---
name: bosskuai-go-development
description: "Use this for Go (Golang) backend, worker, and CLI work — module layout, idiomatic errors, context propagation, goroutines and channels without leaks, net/http or Echo/Chi/Gin services, sqlc/pgx data access, table tests with the race detector, pprof profiling, and small static production builds. Also use to audit or review existing Go code."
---

# BosskuAI Go Development

Use this skill when the code is Go and the answer depends on Go's concurrency model, error conventions, or toolchain rather than on general backend advice.

## How this differs from nearby skills

- **`bosskuai-polyglot-engineering`**: cross-language tradeoffs; this skill is the Go-specific operating manual.
- **`bosskuai-api-design`**: contract shape; this skill implements and hardens the Go handlers behind it.
- **`bosskuai-database-engineering`**: schema and query plans; this skill covers pgx/sqlc usage, pooling, and transaction handling in Go.
- **`bosskuai-performance-profiling`**: generic profiling method; this skill knows pprof, escape analysis, and Go allocation patterns.
- **`bosskuai-docker`** / **`bosskuai-aws-deployment`**: packaging and hosting; this skill produces the binary they ship.

## Mindset

- Errors are values: wrap with `%w`, inspect with `errors.Is`/`errors.As`, handle once, log once.
- Every goroutine has an owner, a stop signal, and a way to report its error. No fire-and-forget.
- `context.Context` is the first parameter of anything that does I/O, waits, or can be cancelled.
- Standard library first; a dependency must earn its place. Accept interfaces, return structs; define interfaces where they are consumed.
- Make the zero value useful; keep packages flat and named for what they provide, not what they contain.

## Orient before changing anything

1. `go.mod`: Go version (loop-variable semantics changed in 1.22; `min`/`max`/`clear` in 1.21; range-over-func in 1.23), module path, replace directives.
2. Layout: `cmd/<binary>` for mains, `internal/` for private packages, no `pkg/` unless something is meant for import by others.
3. Tooling present: `Makefile`, `golangci.yml`, `sqlc.yaml`, migration tool (goose, golang-migrate, atlas), Dockerfile, CI workflow. Match them.
4. Framework: net/http (1.22 mux supports methods and wildcards), Echo, Chi, Gin, or gRPC. Do not introduce a second one.

## Rules that catch most Go bugs

- Goroutine leaks: a `select` without a `ctx.Done()` case, a channel nobody drains, `time.After` inside a loop, a worker without `errgroup`/`WaitGroup`.
- Races: any shared map or slice written from two goroutines; mutex values copied by value; `go test -race` is not optional.
- HTTP clients: `http.DefaultClient` has no timeout. Set `Timeout` or use per-request contexts. Always `defer resp.Body.Close()` and drain the body to reuse connections.
- Servers: set `ReadHeaderTimeout`, `ReadTimeout`, `WriteTimeout`, `IdleTimeout`; shut down with `signal.NotifyContext` + `srv.Shutdown(ctx)`; recover panics per request, never globally swallow them.
- Database: `rows.Close()` and check `rows.Err()`; pass `ctx` to every query; one transaction per unit of work with `defer tx.Rollback()`; pool sizing against the DB's `max_connections`.
- Nil interfaces: a nil `*T` stored in an interface is not `nil`. Return `nil` explicitly.
- Slices: `append` on a sub-slice can overwrite the parent; copy when handing out slices of internal state.
- Errors: never `err != nil` then continue with the zero value; never `panic` for expected failures; sentinel errors exported, wrapped with context at each layer, translated to HTTP status at the edge only.
- `init()` doing I/O or reading env is a testability bug; wire in `main`.
- JSON: unexported fields are silent no-ops; `omitempty` on structs does nothing; use `json.RawMessage` for pass-through payloads.
- sqlc/pgx: Postgres `interval` maps to `pgtype.Interval`, not `time.Duration`; `numeric` needs `pgtype.Numeric` or `shopspring/decimal`; nullable columns become `pgtype.Text` etc. Do the conversion in one place.
- Time: store UTC, convert at the edge; `time.Now()` in business logic makes tests flaky — inject a clock.

## Service structure that scales

- `main.go` wires config, logger, DB, HTTP; no business logic.
- Config from env with validation at startup (fail fast, print which key is missing, never the value).
- `log/slog` structured logging with request id, trace id, and no PII; one logger passed down, not a global.
- Middleware order: request id → recover → timeout → auth → handler. Health (`/healthz` liveness, `/readyz` checks DB) unauthenticated.
- Workers: `errgroup.WithContext`, bounded concurrency (semaphore or worker pool), idempotent jobs, retries with jitter, and a dead-letter path.
- Outbox pattern for "write DB and publish event" to avoid dual-write loss.

## Testing

- Table-driven tests with `t.Run` and `t.Parallel()` where safe; name cases by behavior.
- `httptest` for handlers; `testcontainers-go` or a Docker-provided Postgres for repositories (not sqlite substitutes); golden files for serialized output.
- `go test -race -count=1 ./...` in CI; `-shuffle=on` to catch order dependence; fuzz tests for parsers and decoders.
- Benchmarks with `b.ReportAllocs()`; compare with `benchstat` before claiming a speedup.

## Performance

- Profile before optimizing: `net/http/pprof` in non-public listeners, `go tool pprof` for cpu, heap, mutex, block, goroutine.
- `go build -gcflags=-m` shows escapes; preallocate slices with known length; `strings.Builder`; `sync.Pool` only for measured hot buffers.
- In containers set `GOMEMLIMIT` and use `automaxprocs` (or Go 1.25+ container-aware `GOMAXPROCS`) so the runtime respects cgroup limits.

## Production build

```bash
CGO_ENABLED=0 GOOS=linux GOARCH=arm64 go build -trimpath -ldflags="-s -w -X main.version=$(git rev-parse --short HEAD)" -o bin/app ./cmd/app
```

Distroless or `scratch` base with CA certs and tzdata; non-root user; one binary per image.

## Verification

```bash
gofmt -l . && go vet ./...
golangci-lint run          # or staticcheck ./...
go test -race -count=1 ./...
govulncheck ./...
```

## Guardrails

- Do not add a framework, ORM, or DI container to a codebase that already runs fine on the standard library.
- Do not ignore returned errors (including `Close`, `Write`, `Flush`) to quiet the linter.
- Do not share `*sql.Tx` or `pgx.Tx` across goroutines.
- Do not claim concurrency-safe without a race-detector run.
- Do not put secrets in flags or build args; env or a secrets manager only.

## Output format

```text
Go version: [x.y] - Layout: [cmd/internal] - Framework: [net/http | Echo | Chi | Gin | gRPC]
Tooling honored: [linters, sqlc, migrations, CI]

Findings:
  P0/P1/P2 - [file:line] - [issue] - [fix]

Change plan: [smallest correct change]
Verification: [commands run and result; race detector status]
```

## References

- `../../references/checklists/go-development-checklist.md`
- `../../references/checklists/api-design-checklist.md`
- `../../references/checklists/database-engineering-checklist.md`
