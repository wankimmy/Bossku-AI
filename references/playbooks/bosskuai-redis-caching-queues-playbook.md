# BosskuAI Redis Caching & Queues Playbook

Senior-level audit and implementation reference for Redis-backed caches and queue workers in Laravel and Nitro/Node apps. Each section pairs the wrong-way pattern with the right-way fix and the verification step that proves it.

## Audit flow

1. Read `config/cache.php`, `config/queue.php`, `config/database.php` (Redis section), `config/horizon.php`, plus `redis.conf` or hosting equivalent.
2. List every `Cache::*`, `Redis::*`, `Queue::*`, and direct `*->dispatch(...)` call site.
3. Inspect Horizon dashboard (or whatever the queue UI is) for failed jobs, throughput, and worker memory.
4. Check `redis-cli INFO memory`, `INFO stats`, `SLOWLOG GET 50`, and `CLIENT LIST` on production.
5. Verify with `php artisan queue:failed`, `php artisan horizon:status`, a load test on the cache path, and a forced job retry.

## Best-practice checks (one-liner version)

- All cache keys are scoped by tenant and version (`tenant:{id}:user:{uid}:v3`).
- Every `Cache::remember`/`Cache::put` has an explicit TTL — no infinite cache by accident.
- Eviction policy is `allkeys-lru` (cache) or `noeviction` (queue/auth) and **separated** when both use cases share Redis.
- `timeout` < `retry_after` for every queue connection.
- Failed-job alerting is wired to Slack/email/PagerDuty.
- Locks have an explicit owner token and expiry; never `SET key 1` with no TTL.
- `redis-cli SLOWLOG GET 50` reviewed weekly; entries above 10ms triaged.
- Horizon (or worker manager) restart hook fires on every deploy.
- Queue workers run with bounded memory (`--max-jobs`, `--max-time`) so leaks don't compound.

## Recommended commands

```bash
redis-cli INFO memory
redis-cli INFO stats
redis-cli SLOWLOG GET 50
redis-cli CLIENT LIST
redis-cli --bigkeys
php artisan queue:failed
php artisan queue:retry all
php artisan horizon:status
php artisan horizon:terminate     # graceful restart on deploy
```

---

## Worked anti-patterns and fixes

### 1. Cache stampede (dog-pile) on a hot key

**Wrong**

```php
$products = Cache::remember('top-products', 600, function () {
    return Product::with('vendor')->orderByDesc('sold')->limit(50)->get();
});
```

When the key expires under traffic, every concurrent request runs the expensive query at once. The DB falls over.

**Right** — single-flight via lock:

```php
$products = Cache::remember('top-products', 600, function () {
    return Cache::lock('top-products:lock', 10)->block(5, function () {
        return Product::with('vendor')->orderByDesc('sold')->limit(50)->get();
    });
});
```

For very hot keys, prefer **stale-while-revalidate**: serve the old value, refresh asynchronously via a queued job.

```php
$products = Cache::get('top-products') ?? Product::query()->...->get();   // serve, possibly stale
RefreshTopProducts::dispatchUnique();                                      // background refresh
```

**Verify** — run a load test (e.g. `oha -n 1000 -c 50 https://...`) hitting the endpoint with Redis flushed. Without the fix, you'll see a DB CPU spike and slow responses; with the fix, only one query runs.

### 2. Cross-tenant cache key collision

**Wrong**

```php
Cache::remember("user:{$id}:profile", 300, fn () => User::find($id)->profile);
```

`$id` is unique per user but not per tenant — and a misconfigured tenancy can let tenant A read tenant B's cached value.

**Right**

```php
$tenantId = app(TenantContext::class)->id();
Cache::remember("t:{$tenantId}:user:{$id}:profile:v2", 300, fn () => ...);
```

Always include:
- tenant ID,
- a version segment so you can invalidate by bumping the version,
- the schema version when the underlying shape changes.

**Verify** — `redis-cli --scan --pattern '*'` and grep for keys missing tenant scoping.

### 3. No TTL on a "small" key that turns into a memory leak

**Wrong**

```php
Cache::forever('feature-flags', $flags);
```

`forever` looks fine until you start writing per-user variants:

```php
Cache::forever("user:{$id}:flags", $flags);   // millions of keys, no expiry
```

Memory climbs until Redis OOMs.

**Right** — explicit TTL even for "static" data:

```php
Cache::put("user:{$id}:flags", $flags, now()->addHours(6));
```

For genuinely static data, prefer `config()` or filesystem cache.

**Verify** — `redis-cli INFO memory | grep used_memory_human` over time. The number should plateau, not climb.

### 4. Wrong eviction policy when cache and queue share Redis

**Wrong** — single Redis instance with `maxmemory-policy allkeys-lru`. Under memory pressure, queue jobs get evicted and silently disappear.

**Right** — separate logical Redis databases or instances per use case, with per-instance policy:

| Use case | Policy           | Why                                     |
|----------|------------------|-----------------------------------------|
| Cache    | `allkeys-lru`    | Old values OK to drop                   |
| Queue    | `noeviction`     | Jobs must never disappear silently      |
| Sessions | `volatile-lru`   | Drop expired sessions only              |
| Locks    | `noeviction`     | Losing locks corrupts critical sections |

In `config/database.php`:

```php
'redis' => [
    'cache'    => ['host' => ..., 'database' => 1],
    'queue'    => ['host' => ..., 'database' => 2],
    'sessions' => ['host' => ..., 'database' => 3],
],
```

**Verify** — `redis-cli CONFIG GET maxmemory-policy` on each instance. Confirm by tailing the queue during a memory-pressure load test; failed jobs should not appear from eviction.

### 5. `timeout` >= `retry_after` causes double-execution

**Wrong**

```php
// config/queue.php
'connections' => [
  'redis' => [
    'driver'      => 'redis',
    'queue'       => 'default',
    'retry_after' => 60,
  ],
],

// job class
public $timeout = 90;
```

`retry_after` says "if not done in 60s, give it to another worker." But `timeout` lets the job run for 90s. So at 60s a second worker grabs the same job → it runs twice.

**Right** — always `timeout < retry_after - safety_margin`:

```
timeout = 50   retry_after = 90
```

Plus make the job idempotent (see Laravel playbook §4) so even a race doesn't corrupt state.

**Verify** — read every job's `$timeout` and the connection's `retry_after`. Write a unit test that fails if `timeout >= retry_after - 5`.

### 6. Lock without expiry → deadlock on crash

**Wrong**

```php
$lock = Cache::lock('payout-batch');
$lock->get();          // no expiry — if the worker crashes, the key never releases
```

A worker crash leaves the lock held forever. New workers block until manual intervention.

**Right** — explicit expiry plus owner token:

```php
$lock = Cache::lock('payout-batch', 300);    // auto-releases after 5min
if ($lock->get()) {
    try {
        // ... critical section
    } finally {
        $lock->release();                    // own-token release; ignored if expired
    }
}
```

For long-running work that may exceed expiry, extend periodically:

```php
while (! $batch->done()) {
    $lock->block(0);                  // re-acquire with same owner
    $batch->processNextChunk();
}
```

**Verify** — kill -9 a worker mid-job in staging. The lock must release within its TTL; the next worker must pick up cleanly.

### 7. Failed jobs accumulate without alerting

**Wrong**

```bash
$ php artisan queue:failed
+----+...+
| 4827 failed jobs                |
```

Nobody noticed, customers' orders are stuck.

**Right** — wire failures to a real alerting channel:

```php
// app/Providers/AppServiceProvider.php
Queue::failing(function (JobFailed $event) {
    Log::error('queue.job.failed', [
        'job'        => $event->job->getName(),
        'connection' => $event->connectionName,
        'exception'  => $event->exception->getMessage(),
    ]);

    if (app()->environment('production')) {
        Notification::route('slack', config('alerts.slack_webhook'))
            ->notify(new QueueJobFailed($event));
    }
});
```

Plus a daily Horizon metric: number of failed jobs in last 24h must be 0 (or below your accepted floor).

**Verify** — manually trigger a failure (`Queue::push(new BadJob)`). Slack/PagerDuty must page within seconds.

### 8. Horizon tags missing → impossible to triage

**Wrong** — Horizon dashboard shows job classes only. When `ProcessOrder` fails 200 times, you have no idea which orders.

**Right** — implement `tags()` on every job:

```php
class ProcessOrder implements ShouldQueue
{
    public function tags(): array
    {
        return ["tenant:{$this->order->tenant_id}", "order:{$this->order->id}"];
    }
}
```

Then Horizon's failed-job page lets you filter to a single tenant or order.

### 9. Queue worker memory leak

**Wrong**

```bash
php artisan queue:work --queue=default
```

Long-lived process. Eloquent boot caches, log buffers, and singletons accumulate. Memory climbs until OOM.

**Right** — bounded workers with supervisor restart:

```bash
php artisan queue:work --queue=default \
    --max-jobs=1000 \
    --max-time=3600 \
    --memory=512
```

`max-jobs` and `max-time` cause graceful exit; supervisord/systemd restarts the process. Combined with `--memory`, runaway leaks self-heal.

For Horizon, set in `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'maxProcesses' => 10,
            'maxJobs'      => 1000,
            'maxTime'      => 3600,
            'memory'       => 512,
            'tries'        => 3,
            'timeout'      => 90,
        ],
    ],
],
```

**Verify** — graph worker RSS memory. Should sawtooth (climb then reset on restart), not climb monotonically.

### 10. SLOWLOG ignored

**Wrong** — Redis runs fine for a year. One day a single command takes 4 seconds and blocks every other client (Redis is single-threaded).

**Right** — review SLOWLOG weekly and on every incident:

```bash
redis-cli SLOWLOG GET 50
redis-cli CONFIG SET slowlog-log-slower-than 10000   # 10ms threshold (microseconds)
```

Common slow commands to watch for:

- `KEYS *` in production code → replace with `SCAN`.
- `HGETALL` on million-field hashes → restructure the hash.
- Big `LRANGE 0 -1` on a list → paginate or use a stream.
- `SORT` on large sets → precompute.

**Verify** — set the threshold to 10ms and run for a day. Any entries get triaged before merging more cache code.

### 11. Cache invalidation misses

**Wrong** — write path updates the DB but forgets the cache:

```php
$user->update(['email' => $newEmail]);   // cache key user:{id}:profile is now stale
```

**Right** — explicit invalidation, ideally as a model observer:

```php
// app/Observers/UserObserver.php
public function saved(User $user): void
{
    Cache::forget("t:{$user->tenant_id}:user:{$user->id}:profile:v2");
}
```

Or version-based: bump a global version key, embed it in cache keys. Never have to know every dependent key.

```php
$v = Cache::increment('user-profile:version');
Cache::put("user:{$id}:profile:v{$v}", $profile, 300);
```

**Verify** — write a test that updates the underlying record and asserts the next read does not return the stale value.

---

## Production audit matrix

| Layer       | Check                                                | Tool / command                              |
|-------------|------------------------------------------------------|---------------------------------------------|
| Memory      | `used_memory_human` plateaus, not climbs             | `redis-cli INFO memory`                     |
| Eviction    | Cache vs queue separated, correct policy each        | `redis-cli CONFIG GET maxmemory-policy`     |
| Slow log    | No entries above threshold reviewed weekly           | `redis-cli SLOWLOG GET 50`                  |
| Big keys    | No keys above sane size                              | `redis-cli --bigkeys`                       |
| Connections | Client count stable; no leak                         | `redis-cli CLIENT LIST \| wc -l`             |
| Cache hit   | Hit rate per key family > 80%                        | `INFO stats` + app metrics                  |
| Stampede    | Hot keys protected by lock or SWR                    | code review                                 |
| Tenant scope| All cache keys include tenant prefix                 | grep `Cache::*`                             |
| Queue       | `timeout < retry_after - 5`                           | code review                                 |
| Failed jobs | Alert wired; current failed count = 0                | `php artisan queue:failed`                  |
| Locks       | Every lock has TTL                                   | code review                                 |
| Workers     | `max-jobs` and `max-time` set                        | supervisor / Horizon config                 |
| Restart     | `horizon:terminate` runs on deploy                   | deploy script                               |

## Output expectation

When auditing, return:

1. **Findings table** — file:line or Redis key/command, severity, evidence, fix.
2. **Smallest fix sequence** — minimum P0/P1 set to ship.
3. **Verification** — exact command, metric, or graph that proves each fix.
4. **De-scope** — what is intentionally not touched yet, and why.

## Further reading

- `bosskuai-laravel-development-playbook.md` — job idempotency patterns referenced above.
- `bosskuai-database-engineering-playbook.md` — index strategies that reduce the cache surface needed.
- `bosskuai-vps-docker-deployment-playbook.md` — Redis in Docker Compose, persistence, AOF, backup.
