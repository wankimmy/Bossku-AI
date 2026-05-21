<?php

namespace App\Services\Data;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DataExplorerService
{
    /** @var list<string> */
    protected const BLOCKED_TABLES = [
        'migrations',
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /** @return list<string> */
    public function allowedTables(): array
    {
        $tables = [];
        foreach (Schema::getTableListing() as $name) {
            if (str_starts_with($name, 'bossku_ai_') && ! in_array($name, self::BLOCKED_TABLES, true)) {
                $tables[] = $name;
            }
        }
        sort($tables);

        return $tables;
    }

    public function assertAllowedTable(string $table): void
    {
        if (! in_array($table, $this->allowedTables(), true)) {
            throw new \InvalidArgumentException('Table not allowed: '.$table);
        }
    }

    /**
     * @return array{tables: list<array<string, mixed>>}
     */
    public function listTables(): array
    {
        $out = [];
        foreach ($this->allowedTables() as $name) {
            $count = (int) DB::table($name)->count();
            $columns = $this->columnMeta($name);
            $out[] = [
                'name' => $name,
                'label' => $this->humanLabel($name),
                'row_count' => $count,
                'columns' => $columns,
            ];
        }

        return ['tables' => $out];
    }

    /**
     * @return array<string, mixed>
     */
    public function listRows(string $table, int $page, int $perPage, ?string $search, ?string $sortCol, string $sortDir): array
    {
        $this->assertAllowedTable($table);
        $perPage = min(50, max(1, $perPage));
        $page = max(1, $page);
        $columns = $this->columnNames($table);
        $pk = $this->primaryKeyColumn($table, $columns);

        $query = DB::table($table);
        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $driver = (string) DB::connection()->getDriverName();
            $query->where(function ($q) use ($columns, $term, $table, $driver) {
                foreach ($columns as $col) {
                    $type = $this->columnType($table, $col);
                    if (in_array($type, ['string', 'text'], true) || str_contains($type, 'char')) {
                        if ($driver === 'pgsql') {
                            $q->orWhere($col, 'ilike', $term);
                        } else {
                            $q->orWhere($col, 'like', $term);
                        }
                    }
                }
            });
        }

        if ($sortCol !== null && in_array($sortCol, $columns, true)) {
            $query->orderBy($sortCol, strtolower($sortDir) === 'asc' ? 'asc' : 'desc');
        } elseif ($pk !== null) {
            $query->orderByDesc($pk);
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();
        $formatted = [];
        foreach ($rows as $row) {
            $formatted[] = $this->formatRow($table, (array) $row, $columns, full: false);
        }

        return [
            'table' => $table,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'primary_key' => $pk,
            'rows' => $formatted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRow(string $table, string $id): array
    {
        $this->assertAllowedTable($table);
        $columns = $this->columnNames($table);
        $pk = $this->primaryKeyColumn($table, $columns);
        if ($pk === null) {
            throw new \RuntimeException('No primary key for table '.$table);
        }

        $row = DB::table($table)->where($pk, $id)->first();
        if ($row === null) {
            throw new \InvalidArgumentException('Row not found');
        }

        return $this->formatRow($table, (array) $row, $columns, full: true);
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    protected function formatRow(string $table, array $row, array $columns, bool $full = false): array
    {
        $out = [];
        $settingsKey = $table === 'bossku_ai_settings' ? (string) ($row['key'] ?? '') : null;
        foreach ($columns as $col) {
            $value = $row[$col] ?? null;
            $out[$col] = $this->formatCell($table, $col, $value, $full, $settingsKey);
        }
        $out['_links'] = $this->linkHints($row);

        return $out;
    }

    protected function formatCell(string $table, string $col, mixed $value, bool $full, ?string $settingsKey = null): mixed
    {
        if ($this->shouldMask($table, $col, $settingsKey)) {
            return '••••••••';
        }
        if ($value === null) {
            return null;
        }
        if (is_string($value) && $this->looksLikeJson($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if (! $full && is_string($pretty) && mb_strlen($pretty) > 500) {
                    return mb_substr($pretty, 0, 500).'…';
                }

                return $pretty;
            } catch (\Throwable) {
                // fall through
            }
        }
        if (is_string($value) && ! $full && mb_strlen($value) > 500) {
            return mb_substr($value, 0, 500).'…';
        }

        return $value;
    }

    protected function shouldMask(string $table, string $col, ?string $settingsKey = null): bool
    {
        if (preg_match('/password|secret|encrypted|api_key|token/i', $col)) {
            return true;
        }
        if ($table === 'bossku_ai_settings' && $col === 'value' && $settingsKey !== null && $settingsKey !== '') {
            if (preg_match('/encrypted|api_key|secret/i', $settingsKey)) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeJson(string $value): bool
    {
        $t = trim($value);

        return ($t !== '' && (($t[0] === '{' && str_ends_with($t, '}')) || ($t[0] === '[' && str_ends_with($t, ']'))));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    protected function linkHints(array $row): array
    {
        $links = [];
        if (isset($row['run_id']) && is_string($row['run_id']) && $row['run_id'] !== '') {
            $links['run'] = '/runs/'.$row['run_id'];
        }
        if (isset($row['skill_id']) && is_string($row['skill_id']) && $row['skill_id'] !== '') {
            $links['skill'] = '/skills/'.$row['skill_id'];
        }

        return $links;
    }

    /** @return list<array{name: string, type: string}> */
    protected function columnMeta(string $table): array
    {
        $out = [];
        foreach (Schema::getColumns($table) as $col) {
            $out[] = [
                'name' => $col['name'],
                'type' => $col['type_name'] ?? $col['type'] ?? 'unknown',
            ];
        }

        return $out;
    }

    /** @return list<string> */
    protected function columnNames(string $table): array
    {
        return array_map(fn ($c) => $c['name'], Schema::getColumns($table));
    }

    protected function columnType(string $table, string $col): string
    {
        foreach (Schema::getColumns($table) as $c) {
            if ($c['name'] === $col) {
                return (string) ($c['type_name'] ?? $c['type'] ?? 'string');
            }
        }

        return 'string';
    }

    /** @param  list<string>  $columns */
    protected function primaryKeyColumn(string $table, array $columns): ?string
    {
        if ($table === 'bossku_ai_settings' && in_array('key', $columns, true)) {
            return 'key';
        }
        if (in_array('id', $columns, true)) {
            return 'id';
        }
        foreach ($columns as $col) {
            if (str_ends_with($col, '_id') && $col !== 'run_id') {
                return $col;
            }
        }

        return $columns[0] ?? null;
    }

    protected function humanLabel(string $table): string
    {
        $base = str_replace('bossku_ai_', '', $table);

        return Str::title(str_replace('_', ' ', $base));
    }
}
