<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataExplorerApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tables_index_returns_bossku_tables(): void
    {
        Run::query()->create([
            'prompt' => 'test',
            'status' => 'completed',
        ]);

        $this->getJson('/api/data/tables')
            ->assertOk()
            ->assertJsonStructure(['tables' => [['name', 'label', 'row_count', 'columns']]]);
    }

    #[Test]
    public function table_rows_paginated(): void
    {
        Run::query()->create(['prompt' => 'a', 'status' => 'running']);
        Run::query()->create(['prompt' => 'b', 'status' => 'completed']);

        $expected = Run::query()->count();

        $this->getJson('/api/data/tables/bossku_ai_runs?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('total', $expected)
            ->assertJsonCount(1, 'rows');
    }

    #[Test]
    public function invalid_table_returns_404(): void
    {
        $this->getJson('/api/data/tables/users')
            ->assertNotFound();
    }
}
