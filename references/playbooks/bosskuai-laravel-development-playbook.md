# BosskuAI Laravel Development Playbook

Senior-level audit and implementation reference for Laravel 11/12 production apps. Each section pairs the wrong-way pattern with the right-way fix and the verification step that proves it.

## Audit flow

1. Read `composer.json`, `routes/*`, `app/Models`, `app/Http/Requests`, `app/Policies`, `app/Jobs`, `app/Listeners`, migrations, factories, and tests.
2. Identify Laravel version, PHP version, and package constraints (`composer.lock`).
3. Trace one critical request end-to-end: route → middleware → controller → Form Request → policy → service/action → transaction → model/query → event/job → API Resource → response.
4. Check tenancy, auth, queues, scheduled tasks, webhooks, file uploads, notifications, and external integrations.
5. Verify with `php artisan test`, PHPStan/Larastan, Pint, targeted SQL/log inspection, and a narrow manual reproduction when available.

## Laravel 11/12 best-practice checks (one-liner version)

- Form Requests at every write boundary.
- Policies/gates for every tenant- or user-sensitive action.
- Transactions around multi-step state changes; dispatch jobs/events after commit.
- Jobs are idempotent and retry-safe; `timeout` < `retry_after`.
- Events/listeners do not hide critical synchronous failures.
- Migrations include database constraints (UNIQUE, FK, CHECK) where business rules require them.
- Eloquent relationships eager-loaded in list endpoints.
- API Resources never leak internal columns (passwords, secrets, internal IDs, soft-delete columns).
- Config comes from `config/*`, not scattered `env()` calls outside config files.
- Logs include correlation IDs and never include secrets/PII.

## Recommended commands

```bash
composer validate
composer audit --locked
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
php artisan route:list --except-vendor
php artisan queue:failed
php artisan about
```

---

## Worked anti-patterns and fixes

### 1. N+1 in a list endpoint

**Wrong**

```php
// app/Http/Controllers/OrderController.php
public function index()
{
    $orders = Order::where('tenant_id', auth()->user()->tenant_id)->get();
    return OrderResource::collection($orders);
}

// app/Http/Resources/OrderResource.php
'customer_name' => $this->customer->name,
'item_count'    => $this->items()->count(),
```

Each order triggers two extra queries. 50 orders → 101 queries.

**Right**

```php
$orders = Order::query()
    ->where('tenant_id', auth()->user()->tenant_id)
    ->with('customer:id,name')                  // constrained eager load
    ->withCount('items')                        // single aggregate query
    ->paginate(25);
```

**Verify**

```bash
# In a test or via Telescope/Debugbar
DB::enableQueryLog();
$this->getJson('/api/orders');
$this->assertLessThan(5, count(DB::getQueryLog()));
```

Or in production, sample with the SQL slow log and confirm `EXPLAIN` shows index use on `(tenant_id, created_at)`.

### 2. Missing Form Request authorization

**Wrong**

```php
public function update(Request $request, Order $order)
{
    $request->validate(['status' => 'required|in:open,closed']);
    $order->update($request->only('status'));
    return $order;
}
```

No tenancy check, no policy, raw `$request->only()`. A user from tenant A can mutate tenant B's order if they guess the ID.

**Right**

```php
// app/Http/Requests/UpdateOrderRequest.php
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('order'));
}
public function rules(): array
{
    return ['status' => ['required', Rule::in(OrderStatus::cases())]];
}

// app/Policies/OrderPolicy.php
public function update(User $user, Order $order): bool
{
    return $user->tenant_id === $order->tenant_id
        && $user->hasPermission('order:update');
}

// Controller
public function update(UpdateOrderRequest $request, Order $order)
{
    $order->update($request->validated());
    return new OrderResource($order);
}
```

**Verify** — write a test that asserts a cross-tenant request returns 403 *and* that the row was not modified.

### 3. Soft-delete uniqueness across MySQL/MariaDB/PostgreSQL/SQLite

A common bug: a unique index on `email` blocks soft-deleted rows from being recreated, or allows duplicates depending on the driver.

**Wrong**

```php
$table->string('email')->unique();
$table->softDeletes();
```

**Right — PostgreSQL** (partial unique index):

```php
DB::statement('
    CREATE UNIQUE INDEX users_email_active_unique
    ON users (email)
    WHERE deleted_at IS NULL
');
```

**Right — MySQL/MariaDB** (generated column):

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('email_active')
        ->virtualAs("CASE WHEN deleted_at IS NULL THEN email ELSE NULL END")
        ->nullable();
    $table->unique('email_active');
});
```

**Right — SQLite** (partial index, syntax differs slightly):

```sql
CREATE UNIQUE INDEX users_email_active_unique ON users(email) WHERE deleted_at IS NULL;
```

Tests run on SQLite by default — confirm the constraint behaves the same on the production driver, not just the test driver.

### 4. Job not idempotent — duplicate side effects on retry

**Wrong**

```php
public function handle(): void
{
    $invoice = Invoice::find($this->invoiceId);
    Stripe::charges()->create([
        'amount'   => $invoice->total_cents,
        'customer' => $invoice->customer->stripe_id,
    ]);
    $invoice->update(['paid_at' => now()]);
}
```

If the job times out *after* charging but before updating, the retry charges the customer twice.

**Right**

```php
public function uniqueId(): string
{
    return "invoice-charge-{$this->invoiceId}";
}

public function handle(): void
{
    DB::transaction(function () {
        $invoice = Invoice::lockForUpdate()->find($this->invoiceId);
        if ($invoice->paid_at) return;                       // idempotent guard

        $idempotencyKey = "invoice-{$this->invoiceId}-attempt";
        $charge = Stripe::charges()->create(
            ['amount' => $invoice->total_cents, 'customer' => $invoice->customer->stripe_id],
            ['idempotency_key' => $idempotencyKey]            // Stripe-level idempotency
        );
        $invoice->update(['paid_at' => now(), 'stripe_charge_id' => $charge->id]);
    });
}

// implements ShouldBeUnique
```

**Also check**: `timeout` (e.g. 60s) < `retry_after` (e.g. 90s). If `timeout` ≥ `retry_after`, the same job can be picked up twice and run in parallel — the same root cause as the double-charge.

### 5. Webhook with no signature verification or replay protection

**Wrong**

```php
Route::post('/webhooks/stripe', function (Request $request) {
    $event = json_decode($request->getContent(), true);
    Event::dispatch(new StripeWebhookReceived($event));
    return response('ok');
});
```

Anyone can POST a fake event.

**Right**

```php
Route::post('/webhooks/stripe', function (Request $request) {
    try {
        $event = Webhook::constructEvent(
            $request->getContent(),
            $request->header('Stripe-Signature'),
            config('services.stripe.webhook_secret')          // constant-time comparison inside SDK
        );
    } catch (SignatureVerificationException $e) {
        return response('invalid', 400);
    }

    // Replay protection: persist external event ID with a unique constraint.
    if (WebhookEvent::where('external_id', $event->id)->exists()) {
        return response('duplicate', 200);
    }
    WebhookEvent::create(['external_id' => $event->id, 'type' => $event->type, 'payload' => $event]);

    ProcessStripeWebhook::dispatch($event->id);               // queue, don't process inline
    return response('ok');
})->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

**Verify** — fuzz test: send the same event ID twice, send wrong signature, send replayed event from 1h ago. Each must produce the documented status code.

### 6. Transactions and event dispatch order

**Wrong**

```php
DB::beginTransaction();
$order = Order::create([...]);
event(new OrderCreated($order));   // listener emails the customer
DB::commit();
```

If the commit later fails, the customer already got an email about an order that doesn't exist.

**Right**

```php
DB::transaction(function () use (&$order) {
    $order = Order::create([...]);
});
event(new OrderCreated($order));   // dispatched only after commit succeeds
```

For automatic deferral on listeners that should always wait, implement `ShouldHandleEventsAfterCommit` (Laravel 10+) on the listener.

### 7. Tenant scoping that leaks via relations

**Wrong**

```php
// User has tenant_id; Order has tenant_id; OrderItem does NOT.
$user->orders()->with('items')->get();        // safe, scoped through orders
OrderItem::where('sku', $sku)->first();        // UNSCOPED — leaks across tenants
```

**Right**

Apply a global scope on every tenant-owned model, including the join tables:

```php
// app/Models/Concerns/BelongsToTenant.php
public static function bootBelongsToTenant(): void
{
    static::addGlobalScope('tenant', function (Builder $q) {
        if ($tenantId = app(TenantContext::class)->id()) {
            $q->where($q->getModel()->getTable().'.tenant_id', $tenantId);
        }
    });
    static::creating(function (Model $m) {
        if (! $m->tenant_id) $m->tenant_id = app(TenantContext::class)->id();
    });
}
```

**Verify** — write a test that boots two tenants and asserts that `OrderItem::all()` and direct `whereHas` queries both return only the current tenant's rows. The test must fail if anyone removes the global scope.

### 8. API Resource leaking internals

**Wrong**

```php
public function toArray($request)
{
    return parent::toArray($request);          // dumps every column including password_hash, internal flags
}
```

**Right** — explicit allow-list:

```php
public function toArray($request): array
{
    return [
        'id'         => $this->id,
        'name'       => $this->name,
        'email'      => $this->when($request->user()?->can('viewEmail', $this->resource), $this->email),
        'created_at' => $this->created_at->toIso8601String(),
    ];
}
```

### 9. Octane / Swoole singleton state leak

Octane keeps the framework booted between requests. Anything cached in a singleton survives across requests for *all* users.

**Wrong**

```php
class CurrentUser
{
    public ?User $user = null;
    public function set(User $u) { $this->user = $u; }
}
$this->app->singleton(CurrentUser::class);     // request 1's user leaks into request 2
```

**Right** — use scoped binding (Octane-aware) or read from `auth()` per request:

```php
$this->app->scoped(CurrentUser::class);        // reset per request under Octane
```

Also reset:
- static caches inside helpers,
- `Carbon::setTestNow()` in tests,
- DB connections after fatal errors (`DB::reconnect()`).

Run `php artisan octane:status` and review listed reset hooks.

### 10. `env()` outside config

**Wrong**

```php
// somewhere in a service
$apiKey = env('STRIPE_KEY');
```

When `php artisan config:cache` runs in production, `env()` returns `null` outside config files. Silent failures.

**Right**

```php
// config/services.php
'stripe' => ['key' => env('STRIPE_KEY')],

// service
$apiKey = config('services.stripe.key');
```

---

## Performance and production audit matrix

| Layer       | Check                                            | Tool / command                              |
|-------------|--------------------------------------------------|---------------------------------------------|
| Routes      | No vendor noise in `route:list`                  | `php artisan route:list --except-vendor`    |
| Queries     | EXPLAIN on N most-hit queries                    | slow query log + `EXPLAIN ANALYZE`           |
| Indexes     | Composite indexes match WHERE+ORDER columns      | review schema vs query log                   |
| Eloquent    | No N+1 in list endpoints                         | Telescope / Debugbar / query-count assertion|
| Cache       | Tenant-scoped cache keys, no cross-tenant bleed  | grep `Cache::*` for missing tenant prefix    |
| Queue       | `timeout` < `retry_after`; failed-job alerting  | `config/queue.php` + `queue:failed`          |
| Static      | PHPStan level ≥ 6 (Larastan rules enabled)       | `vendor/bin/phpstan analyse`                 |
| Style       | Pint clean                                       | `vendor/bin/pint --test`                     |
| Deps        | No known CVEs in lock file                       | `composer audit --locked`                    |
| Octane      | No singleton state leaks                         | review bindings, run soak test               |
| Logs        | No secrets/PII; correlation IDs present          | grep + sample audit                          |

## Output expectation

When auditing, return:

1. **Findings table** — file:line, severity (P0/P1/P2), evidence quote, fix.
2. **Smallest fix sequence** — the minimum set of P0/P1 items to ship now.
3. **Verification** — exact command, test, or query result that proves each fix.
4. **De-scope** — what is intentionally not touched yet, and why.

## Further reading

- `bosskuai-database-engineering-playbook.md` — schema/index patterns referenced above.
- `bosskuai-redis-caching-queues-playbook.md` — queue worker tuning, lock patterns, idempotency keys.
- `bosskuai-cybersecurity-risk-playbook.md` — webhook/replay/auth threat models.
